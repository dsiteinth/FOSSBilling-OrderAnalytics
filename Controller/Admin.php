<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Orderanalytics\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Define admin navigation items.
     * Adds "Order Analytics" as a sub-item under the Orders group.
     */
    public function fetchNavigation(): array
    {
        return [
            'subpages' => [
                [
                    'location' => 'order',
                    'index' => 150,
                    'label' => __trans('Order Analytics'),
                    'uri' => $this->di['url']->adminLink('orderanalytics'),
                    'class' => '',
                ],
            ],
        ];
    }

    /**
     * Register admin routes.
     */
    public function register(\Box_App &$app): void
    {
        $app->get('/orderanalytics', 'get_index', [], static::class);
    }

    /**
     * Render the analytics dashboard page.
     */
    public function get_index(\Box_App $app): string
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_orderanalytics_index');
    }
}
