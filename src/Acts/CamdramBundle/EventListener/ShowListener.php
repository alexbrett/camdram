<?php
namespace Acts\CamdramBundle\EventListener;

use Acts\CamdramBundle\Entity\Show;
use Acts\CamdramBundle\Service\ModerationManager;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class ShowListener
{
    private $moderationManager;

    public function __construct(ModerationManager $moderationManager)
    {
        $this->moderationManager = $moderationManager;
    }

    private function updateFields(Show $show, EntityManager $om)
    {
        //ensure the venue attached to the show and to the performances are consistent
        $this->updateVenues($show);
        //ensure the start_at and end_at fields are equal to the start and end of the first and last performances
        $this->updateTimes($show);
    }

    public function prePersist(Show $show, LifecycleEventArgs $event)
    {
        $this->updateFields($show, $event->getObjectManager());
    }

    public function postPersist(Show $show, LifecycleEventArgs $event)
    {
        $this->moderationManager->autoApproveOrEmailModerators($show);
    }

    public function preUpdate(Show $show, PreUpdateEventArgs $event)
    {
        $this->updateFields($show, $event->getObjectManager());

        $em = $event->getObjectManager();
        $uow  = $em->getUnitOfWork();
        $meta = $em->getClassMetadata(get_class($show));
        $uow->recomputeSingleEntityChangeSet($meta, $show);

        //ensure all the associated performances are also saved
        $performanceMeta = $em->getClassMetadata('ActsCamdramBundle:Performance');
        foreach ($show->getPerformances() as $performance) {
            $performance->setShow($show);
            $uow->recomputeSingleEntityChangeSet($performanceMeta, $performance);
        }
    }

    private function updateVenues(Show $show)
    {
        switch ($show->getMultiVenue()) {
            case 'single':
                foreach ($show->getPerformances() as $performance) {
                    $performance->setVenue($show->getVenue());
                    $performance->setVenueName($show->getVenueName());
                }
                break;
            case 'multi':
                //Try to work out the 'main' venue
                //First count venue objects and venue names
                $venues = array();
                $venue_counts = array();
                $name_counts = array();
                foreach ($show->getPerformances() as $performance) {
                    if ($performance->getVenue()) {
                        $key = $performance->getVenue()->getId();
                        if (!isset($venue_counts[$key])) $venue_counts[$key] = 1;
                        else $venue_counts[$key]++;
                        $venues[$key] = $performance->getVenue();
                    }
                    if ($performance->getVenueName()) {
                        $key = $performance->getVenueName();
                        if (!isset($name_counts[$key])) $name_counts[$key] = 1;
                        else $name_counts[$key]++;
                    }
                    //Favour a venue object over a venue name
                    if (count($venue_counts) > 0) {
                        $venue_id = array_search(max($venue_counts), $venue_counts);
                        $show->setVenue($venues[$venue_id]);
                    } else {
                        $venue_name = array_search(max($name_counts), $name_counts);
                        $show->setVenueName($venue_name);
                    }
                }
                break;
        }
    }


    public function updateTimes(Show $show)
    {
        $min = null;
        $max = null;
        foreach ($show->getPerformances() as $performance) {
            if (is_null($min) || $performance->getStartDate() < $min) $min = $performance->getStartDate();
            if (is_null($max) || $performance->getEndDate() > $max) $max = $performance->getEndDate();
        }
        $show->setStartAt($min);
        $show->setEndAt($max);
    }
}
