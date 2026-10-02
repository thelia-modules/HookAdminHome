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

namespace HookAdminHome;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\HookQuery;
use Thelia\Model\ModuleHookQuery;
use Thelia\Module\BaseModule;

class HookAdminHome extends BaseModule
{
    /** @var string */
    public const DOMAIN_NAME = 'hookadminhome';

    /** @var string */
    public const ACTIVATE_NEWS = 'activate_home_news';

    /** @var string */
    public const ACTIVATE_SALES = 'activate_home_sales';

    /** @var string */
    public const ACTIVATE_INFO = 'activate_home_info';

    /** @var string */
    public const ACTIVATE_STATS = 'activate_stats';

    /**
     * 3.0.4 moves the statistics from the top of the home page to its bottom, after the dashboard: the
     * registration of `blockStatistics` on `home.top` left by an older version would render them twice.
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        if (version_compare((string) $currentVersion, '3.0.4', '>=')) {
            return;
        }

        $homeTop = HookQuery::create()->findOneByCode('home.top');

        if (null === $homeTop) {
            return;
        }

        ModuleHookQuery::create()
            ->filterByModuleId(self::getModuleId())
            ->filterByHookId($homeTop->getId())
            ->filterByMethod('blockStatistics')
            ->delete($con);
    }

    /**
     * @return array
     */
    public function getHooks(): array
    {
        return [
            [
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => 'hook_home_stats',
                'title' => 'Hook Home Stats',
                'description' => 'Hook to change default stats',
                'active' => true,
            ],
        ];
    }

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Config/**/*.php',
                __DIR__.'/Tests/*',
                __DIR__.'/HookAdminHome.php',
            ])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
