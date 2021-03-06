<?php

namespace Vacatia\RedirectBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RedirectExceptionListener
{
	private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $request = $event->getRequest();
        $exception = $event->getException();

        if ($exception instanceof NotFoundHttpException) {
            $url = str_replace(array('https://', 'http://'), '', $request->getUri());

            // Do a lookup for a Redirect entity who's old_url matches that of this request
            $redirect = $this->em->getRepository('VacatiaRedirectBundle:Redirect')->findOneByOldUrl($url);

            // If found, redirect to the new_url with the correct status code
            if ($redirect) {
                $event->setResponse(new RedirectResponse($request->getScheme().'://'.$redirect->getNewUrl(), $redirect->getHttpStatusCode()));
                return;
            }

            // If not found, try to match a regex rule
            $redirects = $this->em->getRepository('VacatiaRedirectBundle:Redirect')->findByIsRegex(true);

            foreach ($redirects as $redirect) {
                $oldUrl = str_replace('/', '\/', $redirect->getOldUrl());

                if (preg_match('/'.$oldUrl.'/', $url, $matches)) {
                    $i = 0;
                    $newUrl = $redirect->getNewUrl();

                    foreach ($matches as $match) {
                        $newUrl = str_replace('$'.$i++, $match, $newUrl);
                    }

                    $event->setResponse(new RedirectResponse($request->getScheme().'://'.$newUrl, $redirect->getHttpStatusCode()));
                    return;
                }
            }
        }
    }
}