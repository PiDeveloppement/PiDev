<?php

namespace App\Bundle\NotificationBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class NotificationExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->autowire(\App\Bundle\NotificationBundle\Service\NotificationService::class)
            ->setPublic(true); // NotifierInterface sera injecté automatiquement
    }
}