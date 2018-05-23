<?php

namespace Mautic\PluginBundle\Integration\Auth;

class IntegrationAuthService
{
    protected $authAdapter;

    public function __construct($authType)
    {
    }

    public function prepareRequest()
    {
    }
}
