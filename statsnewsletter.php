<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class statsnewsletter extends ModuleGraph
{
    private $_html = '';
    private $_query = '';
    private $_query2 = '';
    private $_option = '';

    private $table_name;
    private $newsletter_module_name;
    private $newsletter_module_human_readable_name;

    public function __construct()
    {
        $this->name = 'statsnewsletter';
        $this->tab = 'analytics_stats';
        $this->version = '2.0.3';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        $this->table_name = _DB_PREFIX_ . 'emailsubscription';
        $this->newsletter_module_name = 'ps_emailsubscription';
        $this->newsletter_module_human_readable_name = 'Email subscription';

        parent::__construct();

        $this->displayName = $this->trans('Newsletter', array(), 'Admin.Global');
        $this->description = $this->trans('Adds a tab with a graph showing newsletter registrations to the Stats dashboard.', array(), 'Modules.Statsnewsletter.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return (parent::install() && $this->registerHook('displayAdminStatsModules'));
    }

    public function hookDisplayAdminStatsModules($params)
    {
        if (Module::isInstalled($this->newsletter_module_name)) {
            $totals = $this->getTotals();
            if (Tools::getValue('export')) {
                $this->csvExport(array('type' => 'line', 'layers' => 3));
            }
            $this->_html = '
			<div class="panel-heading">
				'.$this->displayName.'
			</div>
			<div class="row row-margin-bottom">
				<div class="col-lg-12">
					<div class="col-lg-8">
						'.$this->engine(array('type' => 'line', 'layers' => 3)).'
					</div>
					<div class="col-lg-4">
						<ul class="list-unstyled">
							<li>'.$this->trans('Customer registrations:', array(), 'Modules.Statsnewsletter.Admin').' '.(int)$totals['customers'].'</li>
							<li>'.$this->trans('Visitor registrations: ', array(), 'Modules.Statsnewsletter.Admin').' '.(int)$totals['visitors'].'</li>
							<li>'.$this->trans('Both:', array(), 'Modules.Statsnewsletter.Admin').' '.(int)$totals['both'].'</li>
						</ul>
						<hr/>
						<a class="btn btn-default export-csv" href="'.Tools::safeOutput($_SERVER['REQUEST_URI'].'&export=1').'">
							<i class="icon-cloud-upload"></i> '.$this->trans('CSV Export', array(), 'Modules.Statsnewsletter.Admin').'
						</a>
					</div>
				</div>
			</div>';
        } else {
            $this->_html = '<p>'.$this->trans('The %s module must be installed.', array($this->newsletter_module_human_readable_name), 'Modules.Statsnewsletter.Admin').'</p>';
        }

        return $this->_html;
    }

    private function getTotals()
    {
        $sql = 'SELECT COUNT(*) as customers
				FROM `'._DB_PREFIX_.'customer`
				WHERE 1
					'.Shop::addSqlRestriction(Shop::SHARE_CUSTOMER).'
					AND `newsletter_date_add` BETWEEN '.ModuleGraph::getDateBetween();
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        $sql = 'SELECT COUNT(*) as visitors
				FROM ' . $this->table_name . '
				WHERE 1
				   '.Shop::addSqlRestriction().'
					AND `newsletter_date_add` BETWEEN '.ModuleGraph::getDateBetween();
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        return array('customers' => $result1['customers'], 'visitors' => $result2['visitors'], 'both' => $result1['customers'] + $result2['visitors']);
    }

    protected function getData($layers)
    {
        $this->_titles['main'][0] = $this->trans('Newsletter statistics', array(), 'Modules.Statsnewsletter.Admin');
        $this->_titles['main'][1] = $this->trans('customers', array(), 'Admin.Global');
        $this->_titles['main'][2] = $this->trans('Visitors', array(), 'Admin.Shopparameters.Feature');
        $this->_titles['main'][3] = $this->trans('Both', array(), 'Admin.Advparameters.Feature');

        $this->_query = 'SELECT newsletter_date_add
				FROM `'._DB_PREFIX_.'customer`
				WHERE 1
					'.Shop::addSqlRestriction(Shop::SHARE_CUSTOMER).'
					AND `newsletter_date_add` BETWEEN ';

        $this->_query2 = 'SELECT newsletter_date_add
				FROM ' . $this->table_name . '
				WHERE 1
					'.Shop::addSqlRestriction(Shop::SHARE_CUSTOMER).'
					AND `newsletter_date_add` BETWEEN ';
        $this->setDateGraph($layers, true);
    }

    protected function setAllTimeValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query.$this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2.$this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 0, 4)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 0, 4)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }

    protected function setYearValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query.$this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2.$this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 5, 2)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 5, 2)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }

    protected function setMonthValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query.$this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2.$this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 8, 2)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 8, 2)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }

    protected function setDayValues($layers)
    {
        $result1 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query.$this->getDate());
        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->_query2.$this->getDate());
        foreach ($result1 as $row) {
            $this->_values[0][(int)substr($row['newsletter_date_add'], 11, 2)] += 1;
        }
        if ($result2) {
            foreach ($result2 as $row) {
                $this->_values[1][(int)substr($row['newsletter_date_add'], 11, 2)] += 1;
            }
        }
        foreach ($this->_values[2] as $key => $zerofill) {
            $this->_values[2][$key] = $this->_values[0][$key] + $this->_values[1][$key];
        }
    }
}
