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

namespace HookAdminHome\Controller;

use HookAdminHome\HookAdminHome;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CustomerQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tools\MoneyFormat;

/**
 * Class HomeController.
 *
 * @author Gilles Bourgeat <gilles@thelia.net>
 */
class HomeController extends BaseAdminController
{
    /**
     * Key prefix for stats cache.
     */
    public const STATS_CACHE_KEY = 'stats';

    public const RESOURCE_CODE = 'admin.home';

    #[Route("/admin/home/stats", name: "admin.home.stats")]
    public function loadStatsAjaxAction(AdapterInterface $cacheAdapter)
    {
        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, [], AccessManager::VIEW)) {
            return $response;
        }

        $cacheExpire = ConfigQuery::getAdminCacheHomeStatsTTL();

        $month = (int) $this->getRequest()->query->get('month', date('m'));
        $year = (int) $this->getRequest()->query->get('year', date('Y'));

        $cacheKey = self::STATS_CACHE_KEY.'_'.$month.'_'.$year;

        $cacheItem = $cacheAdapter->getItem($cacheKey);

        // force flush
        if ($this->getRequest()->query->get('flush', '0')) {
            $cacheAdapter->deleteItem($cacheKey);
        }

        if (!$cacheItem->isHit()) {
            $data = $this->getStatus($month, $year);

            $cacheItem->set(json_encode($data));
            $cacheItem->expiresAfter($cacheExpire);

            if ($cacheExpire) {
                $cacheAdapter->save($cacheItem);
            }
        }

        return $this->jsonResponse($cacheItem->get());
    }

    #[Route("/admin/home/month-sales-block/{month}/{year}", name: "admin.home.month.sales.block", requirements: ["month" => "\d+", "year" => "\d+"])]
    public function blockMonthSalesStatistics(int $month, int $year)
    {
        $baseDate = sprintf('%04d-%02d', $year, $month);

        $startDate = "$baseDate-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $prevMonthStartDate = date('Y-m-01', strtotime("$baseDate -1 month"));
        $prevMonthEndDate = date('Y-m-t', strtotime($prevMonthStartDate));

        $symbol = Currency::getDefaultCurrency()->getSymbol();
        $request = $this->getRequest();

        return $this->render('block-month-sales-statistics.html.twig', [
            'stats_month' => $this->buildStatsPeriod($startDate, $endDate, $symbol, $request),
            'stats_prev_month' => $this->buildStatsPeriod($prevMonthStartDate, $prevMonthEndDate, $symbol, $request),
        ]);
    }

    #[Route("/admin/ajax/thelia_news_feed", name: "admin.news-feed")]
    public function newsFeedAction(): \Symfony\Component\HttpFoundation\Response
    {
        $feedItems = [];

        try {
            $feed = new \SimplePie();
            $feed->set_feed_url('https://github.com/thelia/thelia/commits/main.atom');
            $feed->set_timeout(30);
            $feed->init();

            foreach ($feed->get_items(0, 6) as $item) {
                $feedItems[] = [
                    'title' => $item->get_title() ?? '',
                    'description' => $item->get_description() ?? '',
                    'url' => $item->get_permalink() ?? '#',
                    'date' => $item->get_date('U') ? new \DateTime('@'.$item->get_date('U')) : new \DateTime(),
                ];
            }
        } catch (\Exception $e) {
            // silently fail — template shows empty list
        }

        return $this->render('ajax/thelia_news_feed.html.twig', ['feedItems' => $feedItems]);
    }

    /**
     * @param int $month
     * @param int $year
     *
     * @return \stdClass
     */
    protected function getStatus($month, $year)
    {
        $data = new \stdClass();

        $data->title = $this->getTranslator()->trans(
            'Stats on %month/%year',
            ['%month' => $month, '%year' => $year],
            HookAdminHome::DOMAIN_NAME
        );

        $data->series = [];

        /* sales */
        $data->series[] = $saleSeries = new \stdClass();
        $saleSeries->color = self::testHexColor('sales_color', '#adadad');
        $saleSeries->data = OrderQuery::getMonthlySaleStats($month, $year);
        $saleSeries->valueFormat = '%1.2f '.Currency::getDefaultCurrency()->getSymbol();

        /* new customers */
        $data->series[] = $newCustomerSeries = new \stdClass();
        $newCustomerSeries->color = self::testHexColor('customers_color', '#f39922');
        $newCustomerSeries->data = CustomerQuery::getMonthlyNewCustomersStats($month, $year);
        $newCustomerSeries->valueFormat = '%d';

        /* orders */
        $data->series[] = $orderSeries = new \stdClass();
        $orderSeries->color = self::testHexColor('orders_color', '#5cb85c');
        $orderSeries->data = OrderQuery::getMonthlyOrdersStats($month, $year);
        $orderSeries->valueFormat = '%d';

        /* first order */
        $data->series[] = $firstOrderSeries = new \stdClass();
        $firstOrderSeries->color = self::testHexColor('first_orders_color', '#5bc0de');
        $firstOrderSeries->data = OrderQuery::getFirstOrdersStats($month, $year);
        $firstOrderSeries->valueFormat = '%d';

        /* cancelled orders */
        $data->series[] = $cancelledOrderSeries = new \stdClass();
        $cancelledOrderSeries->color = self::testHexColor('cancelled_orders_color', '#d9534f');
        $cancelledOrderSeries->data = OrderQuery::getMonthlyOrdersStats($month, $year, [5]);
        $cancelledOrderSeries->valueFormat = '%d';

        return $data;
    }

    private function buildStatsPeriod(string $startDate, string $endDate, string $symbol, \Symfony\Component\HttpFoundation\Request $request): array
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

    /**
     * @param string $key
     * @param string $default
     *
     * @return string hexadecimal color or default argument
     */
    protected function testHexColor($key, $default)
    {
        $hexColor = $this->getRequest()->query->get($key, $default);

        return preg_match('/^#[a-f0-9]{6}$/i', $hexColor) ? $hexColor : $default;
    }
}
