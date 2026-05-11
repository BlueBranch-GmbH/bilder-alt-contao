<?php

namespace Bluebranch\BilderAlt\EventListener;

use Symfony\Component\HttpFoundation\RequestStack;
use Contao\CoreBundle\Routing\ScopeMatcher;

trait BackendScopeTrait
{
    public function isFrontend(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return false;
        }

        return $this->scopeMatcher->isFrontendRequest($request);
    }
}
