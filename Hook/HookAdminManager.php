<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HookAdminHome\Hook;

use HookAdminHome\Form\Configuration;
use HookAdminHome\HookAdminHome;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\ParserResolver;

class HookAdminManager extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(Configuration::getName(), data: [
            'enabled-news' => (bool) HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_NEWS, 0),
            'enabled-info' => (bool) HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_INFO, 0),
            'enabled-stats' => (bool) HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_STATS, 0),
            'enabled-sales' => (bool) HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_SALES, 0),
        ]);

        $form->createView();

        $event->add(
            $this->render('admin-home-config.html.twig', ['form' => $form->getView()])
        );
    }
}
