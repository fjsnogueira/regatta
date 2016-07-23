<?php

namespace AppBundle\Controller;

use AppBundle\Entity\RaceSection;
use AppBundle\Entity\Registration;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RaceSectionMoveController extends Controller
{
    public function getPageContentAction(Registration $registration)
    {
        $showUpButton = $this->canBeMovedUp($registration);
        $showDownButton = $this->canBeMovedDown($registration);

        return $this->render(
            'race/_section.move.html.twig',
            array(
                'showUpButton' => $showUpButton,
                'showDownButton' => $showDownButton,
                'registration' => $registration,
            )
        );
    }

    /**
     * Move a registration one lane in front.
     *
     * If the team is already in first lane and there is a section before the
     * current one available (that still accepts changes) then move the team
     * into that one.
     *
     * @Route("/team/{registration}/up", name="registration_move_up")
     * @Method("POST")
     */
    public function moveUpAction(Request $request, Registration $registration)
    {
        // TODO check if user is allowed to modify races
        if (is_null($registration) || !$this->canBeMovedUp($registration)) {
            // TODO error message with flash and redirection
            return new Response('Not possible', Response::HTTP_PRECONDITION_FAILED);
        }

        if ($registration->getLane() > 1) {
            return $this->moveInSection($request, $registration, 'up');
        } else {
            return $this->addToNextSection($request, $registration, 'up');
        }
    }

    /**
     * Move a registration one lane back.
     *
     * If the team is already in last lane and there is a section after the
     * current one available (that still accepts changes) then move the team
     * into that one.
     *
     * @Route("/team/{registration}/down", name="registration_move_down")
     * @Method("POST")
     */
    public function moveDownAction(Request $request, Registration $registration)
    {
        // TODO check if user is allowed to modify races
        if (is_null($registration) || !$this->canBeMovedDown($registration)) {
            // TODO error message with flash and redirection
            return new Response('Not possible', Response::HTTP_PRECONDITION_FAILED);
        }

        // TODO check!
        if (true) {
            return $this->moveInSection($request, $registration, 'down');
        } else {
            return $this->addToNextSection($request, $registration, 'down');
        }
    }

    protected function moveInSection(Request $request, Registration $registration, $direction)
    {
        // TODO preconditions!
        // check if $registration is null --> error message
        // check if $direction has a allowed value --> error message

        $em = $this->getDoctrine()->getManager();
        $section = $registration->getSection();
        $myLane = $registration->getLane();
        $myNewLane = -1;
        if ('up' == $direction) {
            $myNewLane = $myLane - 1;
        } elseif ('down' == $direction) {
            $myNewLane = $myLane + 1;
        }
        if(-1 == $myNewLane) {
            // TODO error message with flash and redirection
            die('moveInSection');
            // TODO remove this method as soon as preconditions check exists
        }

        $other = null;
        // search for the team "directly besides me"
        /** @var Registration $team */
        foreach ($section->getValidRegistrations() as $team) {
            if ($team->getLane() == $myNewLane) {
                $other = $team;
                break;
            }
        }
        // if it exists, then change the position of that
        // team to the one of the moving team
        if (!is_null($other)) {
            $team->setLane($myLane);
            $em->persist($team);
        }
        // change the position of the given team to the new one
        $registration->setLane($myNewLane);
        $em->persist($registration);

        // store in DB
        $em->flush();

        $this->addFlash(
            'notice',
            "Sportler verschoben ({$myLane} >> {$myNewLane})"
        );

        return $this->redirect($request->headers->get('referer'));
    }

    protected function addToNextSection(Request $request, Registration $registration, $direction)
    {
        // TODO preconditions

        // check if $registration is null --> error message
        // check if $direction has a allowed value --> error message

        $f = null;
        if ('up' == $direction) {
            $f = function ($x, $y) {
                return $x < $y;
            };
        } elseif ('down' == $direction) {
            $f = function ($x, $y) {
                return $x > $y;
            };
        }

        if (is_null($f)) {
            // TODO error message with flash and redirection
            die('addToNextSection');
            // TODO remove this method as soon as preconditions check exists
        }

        $next = $this->getNextPossibleSection($registration, $f);
        return new Response('Not implemented', Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Check if there is still a free lane for the given registration to move
     * forward in ranking.
     *
     * @param Registration $registration
     * @return bool <tt>true</tt> if possible
     */
    private function canBeMovedUp(Registration $registration)
    {
        $result = false;
        if ($registration->isValidForRace()) {
            if ($registration->getLane() > 1) {
                $result = true;
            } else {
                $mySectionNumber = $registration->getSection()->getNumber();
                // it is not the first section -> check if any "free"
                // section exists in front
                if ($mySectionNumber > 1) {
                    $next = $this->getNextPossibleSection(
                        $registration,
                        function ($x, $y) {
                            return $x < $y;
                        }
                    );
                    if (!is_null($next)) {
                        $result = true;
                    }
                }
            }
        }

        return $result;
    }

    private function canBeMovedDown(Registration $registration)
    {
        $result = false;
        if ($registration->isValidForRace()) {
            $maxStarters = $registration->getSection()->getRace()->getMaxStarterPerSection();
            if ($registration->getLane() < $maxStarters) {
                $result = true;
            } else {
                // last lane or even higher number than allowed
                $next = $this->getNextPossibleSection(
                    $registration,
                    function ($x, $y) {
                        return $x > $y;
                    }
                );
                if (!is_null($next)) {
                    $result = true;
                }
            }
        }

        return $result;
    }

    /**
     * Search for the next section with free lanes.
     *
     * @param Registration $registration
     * @param \Closure $compareFunction for comparing the number of the inspected lane with the best match
     * @return RaceSection | null
     */
    private function getNextPossibleSection(Registration $registration, $compareFunction)
    {
        $mySectionNumber = $registration->getSection()->getNumber();
        $maxStarters = $registration->getSection()->getRace()->getMaxStarterPerSection();

        $sections = array();
        /** @var RaceSection $section */
        foreach ($registration->getSection()->getRace()->getSections() as $section) {
            if (!$section->isStarted() && !$section->isFinished()) {
                // check for free spaces
                if ($section->getValidRegistrations()->count() < $maxStarters) {
                    $sections[] = $section;
                }
            }
        }
        /** @var RaceSection $next */
        $next = null;
        foreach ($sections as $section) {
            if ($compareFunction($section->getNumber(), $mySectionNumber)) {
                if (is_null($next)) {
                    $next = $section;
                } else {
                    // search the lowest section of those that are higher
                    if (!$compareFunction($next->getNumber(), $section->getNumber())) {
                        $next = $section;
                    }
                }
            }
        }

        return $next;
    }
}