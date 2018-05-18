<?php

namespace Mautic\PluginBundle\Integration\Auth;

interface Authenticates
{
    public function isAuthenticated();

    public function getAuthenticationType();

    public function prepareRequest();

    public function authorizeSession();
}
