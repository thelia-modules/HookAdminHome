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

use HookAdminHome\HookAdminHome;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\ParserResolver;
use Thelia\Core\TheliaKernel;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Currency;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductQuery;
use Thelia\Tools\MoneyFormat;

/**
 * Class AdminHook.
 *
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class AdminHook extends BaseHook
{
    public function __construct(
        private readonly AdapterInterface $theliaCache,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'home.top' => [
                ['type' => 'back', 'method' => 'blockInformation'],
                ['type' => 'back', 'method' => 'blockStatistics'],
            ],
            'home.js' => [
                ['type' => 'back', 'method' => 'blockStatisticsJs'],
                ['type' => 'back', 'method' => 'blockNewsJs'],
            ],
            'home.block' => [
                ['type' => 'back', 'method' => 'blockSalesStatistics'],
                ['type' => 'back', 'method' => 'blockNews'],
                ['type' => 'back', 'method' => 'blockTheliaInformation'],
            ],
            'main.head-css' => [
                ['type' => 'back', 'method' => 'headCss'],
            ],
        ];
    }

    public function headCss(HookRenderEvent $event): void
    {
        $event->add($this->addCSS('assets/css/home.css'));
    }

    public function blockInformation(HookRenderEvent $event): void
    {
        $event->add($this->render('block-information.html.twig', [
            'customerCount' => CustomerQuery::create()->count(),
            'categoryCount' => CategoryQuery::create()->count(),
            'productCount' => ProductQuery::create()->count(),
            'productOnlineCount' => ProductQuery::create()->filterByVisible(1)->count(),
            'productOfflineCount' => ProductQuery::create()->filterByVisible(0)->count(),
            'orderCount' => OrderQuery::create()->count(),
        ]));
    }

    public function blockStatistics(HookRenderEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_STATS, 1)) {
            $event->add($this->render('block-statistics.html.twig'));
        }

        $event->add($this->render('hook-admin-home-config.html.twig'));
    }

    public function blockStatisticsJs(HookRenderEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_STATS, 1)) {
            $event->add($this->render('block-statistics-js.html.twig'));
        }
    }

    public function blockNewsJs(HookRenderEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_NEWS, 1)) {
            $event->add($this->render('block-news-js.html.twig'));
        }
    }

    public function blockSalesStatistics(HookRenderBlockEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_SALES, 1)) {
            $symbol = Currency::getDefaultCurrency()->getSymbol();
            $request = $this->hasRequest() ? $this->getRequest() : null;
            $content = trim($this->render('block-sales-statistics.html.twig', [
                'stats_today' => $this->buildStatsPeriod('today', 'today', $symbol, $request),
                'stats_yesterday' => $this->buildStatsPeriod('yesterday', 'yesterday', $symbol, $request),
                'stats_month' => $this->buildStatsPeriod(
                    (new \DateTime('first day of this month'))->format('Y-m-d'),
                    (new \DateTime('last day of this month'))->format('Y-m-d'),
                    $symbol,
                    $request
                ),
                'stats_prev_month' => $this->buildStatsPeriod(
                    (new \DateTime('first day of last month'))->format('Y-m-d'),
                    (new \DateTime('last day of last month'))->format('Y-m-d'),
                    $symbol,
                    $request
                ),
                'stats_year' => $this->buildStatsPeriod(
                    (new \DateTime('first day of January this year'))->format('Y-m-d'),
                    (new \DateTime('last day of December this year'))->format('Y-m-d'),
                    $symbol,
                    $request
                ),
                'stats_prev_year' => $this->buildStatsPeriod(
                    (new \DateTime('first day of January last year'))->format('Y-m-d'),
                    (new \DateTime('last day of December last year'))->format('Y-m-d'),
                    $symbol,
                    $request
                ),
            ]));

            if (!empty($content)) {
                $event->add([
                    'id' => 'block-sales-statistics',
                    'title' => $this->trans('Sales statistics', [], HookAdminHome::DOMAIN_NAME),
                    'content' => $content,
                ]);
            }
        }
    }

    public function blockNews(HookRenderBlockEvent $event): void
    {
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_NEWS, 1)) {
            $content = trim($this->render('block-news.html.twig'));
            if (!empty($content)) {
                $event->add([
                    'id' => 'block-news',
                    'title' => $this->trans('Thelia Github activity', [], HookAdminHome::DOMAIN_NAME),
                    'content' => $content,
                ]);
            }
        }
    }

    public function blockTheliaInformation(HookRenderBlockEvent $event): void
    {
        $releases = $this->getGithubReleases();
        if (1 == HookAdminHome::getConfigValue(HookAdminHome::ACTIVATE_INFO, 1)) {
            $content = trim(
                $this->render(
                    'block-thelia-information.html.twig',
                    [
                        'latestStableRelease' => $releases['latestStableRelease'],
                        'latestPreRelease' => $releases['latestPreRelease'],
                        'thelia_version' => TheliaKernel::THELIA_VERSION,
                    ]
                )
            );
            if (!empty($content)) {
                $event->add([
                    'id' => 'block-thelia-information',
                    'title' => $this->trans('Thelia news', [], HookAdminHome::DOMAIN_NAME),
                    'content' => $content,
                ]);
            }
        }
    }

    private function buildStatsPeriod(string $startDate, string $endDate, string $symbol, ?\Symfony\Component\HttpFoundation\Request $request): array
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $sales = OrderQuery::getSaleStats($start, $end, true, true);
        $salesNoShipping = OrderQuery::getSaleStats($start, $end, false, true);
        $orderCount = OrderQuery::getOrderStats($start, $end, null);

        $moneyFormat = MoneyFormat::getInstance($request);

        return [
            'salesFormatted' => $moneyFormat->format($sales, null, null, null, $symbol),
            'salesNoShippingFormatted' => $moneyFormat->format($salesNoShipping, null, null, null, $symbol),
            'orderCount' => $orderCount,
            'averageCartFormatted' => $moneyFormat->format(
                $orderCount > 0 ? round($salesNoShipping / $orderCount, 2) : 0,
                null, null, null, $symbol
            ),
        ];
    }

    private function getGithubReleases(): array
    {
        $cachedReleases = $this->theliaCache->getItem('thelia_github_releases');
        if (!$cachedReleases->isHit()) {
            try {
                $resource = curl_init();

                curl_setopt($resource, \CURLOPT_URL, 'https://api.github.com/repos/thelia/thelia/releases');
                curl_setopt($resource, \CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($resource, \CURLOPT_HTTPHEADER, ['accept: application/vnd.github.v3+json']);
                curl_setopt($resource, \CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

                $results = curl_exec($resource);

                curl_close($resource);

                $theliaReleases = json_decode($results, true);

                $publishedAtSort = function ($a, $b) { return (new \DateTime($a['published_at'])) < (new \DateTime($b['published_at'])); };

                $stableReleases = array_filter($theliaReleases, function ($theliaRelease) { return !$theliaRelease['prerelease']; });
                usort($stableReleases, $publishedAtSort);
                $latestStableRelease = $stableReleases[0] ?? null;

                $preReleases = array_filter($theliaReleases, function ($theliaRelease) { return $theliaRelease['prerelease']; });
                usort($preReleases, $publishedAtSort);
                $latestPreRelease = $preReleases[0] ?? null;

                // Don't display pre-release if they are < than stable release
                if (null !== $latestPreRelease && null !== $latestStableRelease
                    && version_compare($latestPreRelease['tag_name'], $latestStableRelease['tag_name'], '<')) {
                    $latestPreRelease = null;
                }
            } catch (\Exception $exception) {
                $latestPreRelease = null;
                $latestStableRelease = null;
            }

            $cachedReleases->expiresAfter(3600);
            $cachedReleases->set([
                'latestStableRelease' => $latestStableRelease,
                'latestPreRelease' => $latestPreRelease,
            ]);
            $this->theliaCache->save($cachedReleases);
        }

        return $cachedReleases->get();
    }
}
