<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class TaxCore
 */
class TaxCore extends ObjectModel
{
    /** @var string|string[] Name */
    public $name;

    /** @var float Rate (%) */
    public $rate;

    /** @var bool active state */
    public $active;

    /** @var bool true if the tax has been historized */
    public $deleted = 0;


    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'     => 'tax',
        'primary'   => 'id_tax',
        'multilang' => true,
        'fields'    => [
            'rate'    => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true, 'size' => 10, 'decimals' => 3],
            'active'  => ['type' => self::TYPE_BOOL, 'dbDefault' => '1'],
            'deleted' => ['type' => self::TYPE_BOOL, 'dbDefault' => '0'],
            /* Lang fields */
            'name'    => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 32],
        ],
    ];


    /**
     * @var array Webservice parameters
     */
    protected $webserviceParameters = [
        'objectsNodeName' => 'taxes',
    ];

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function delete()
    {
        /* Clean associations */
        TaxRule::deleteTaxRuleByIdTax((int) $this->id);

        if ($this->isUsed()) {
            return $this->historize();
        } else {
            return parent::delete();
        }
    }

    /**
     * Save the object with the field deleted to true
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function historize()
    {
        $this->deleted = true;

        return parent::update();
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function toggleStatus()
    {
        if (parent::toggleStatus()) {
            return $this->_onStatusChange();
        }

        return false;
    }

    /**
     * @param bool $nullValues
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function update($nullValues = false)
    {
        if (!$this->deleted && $this->isUsed()) {
            $historizedTax = new Tax($this->id);
            $historizedTax->historize();

            // remove the id in order to create a new object
            $this->id = 0;
            $res = $this->add();

            // change tax id in the tax rule table
            $res = TaxRule::swapTaxId($historizedTax->id, $this->id) && $res;

            return $res;
        } elseif (parent::update($nullValues)) {
            return $this->_onStatusChange();
        }

        return false;
    }

    /**
     * @return bool
     *
     * @deprecated 2.0.0
     * @throws PrestaShopException
     */
    protected function _onStatusChange()
    {
        if (!$this->active) {
            return TaxRule::deleteTaxRuleByIdTax($this->id);
        }

        return true;
    }

    /**
     * Returns true if the tax is used in an order details
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function isUsed()
    {
        return Db::readOnly()->getValue(
            '
		SELECT `id_tax`
		FROM `'._DB_PREFIX_.'order_detail_tax`
		WHERE `id_tax` = '.(int) $this->id
        );
    }

    /**
     * Get all available taxes
     *
     * @param bool $idLang
     * @param bool $activeOnly
     *
     * @return array Taxes
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getTaxes($idLang = false, $activeOnly = true)
    {
        $sql = new DbQuery();
        $sql->select('t.id_tax, t.rate');
        $sql->from('tax', 't');
        $sql->where('t.`deleted` != 1');

        if ($idLang) {
            $sql->select('tl.name, tl.id_lang');
            $sql->leftJoin('tax_lang', 'tl', 't.`id_tax` = tl.`id_tax` AND tl.`id_lang` = '.(int) $idLang);
            $sql->orderBy('`name` ASC');
        }

        if ($activeOnly) {
            $sql->where('t.`active` = 1');
        }

        return Db::readOnly()->getArray($sql);
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function excludeTaxeOption()
    {
        return !Configuration::get('PS_TAX');
    }

    /**
     * Return the tax id associated to the specified name
     *
     * @param string $taxName
     * @param int $active (true by default)
     *
     * @return bool|int
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getTaxIdByName($taxName, $active = 1)
    {
        $tax = Db::readOnly()->getRow(
            '
			SELECT t.`id_tax`
			FROM `'._DB_PREFIX_.'tax` t
			LEFT JOIN `'._DB_PREFIX_.'tax_lang` tl ON (tl.id_tax = t.id_tax)
			WHERE tl.`name` = \''.pSQL($taxName).'\' '.
            ($active == 1 ? ' AND t.`active` = 1' : '')
        );

        return $tax ? (int) $tax['id_tax'] : false;
    }

    /**
     * Returns the ecotax tax rate
     *
     * @param int $idAddress
     *
     * @return float $tax_rate
     *
     * @throws PrestaShopException
     */
    public static function getProductEcotaxRate($idAddress = null)
    {
        $address = Address::initialize($idAddress);

        $taxManager = TaxManagerFactory::getManager($address, (int) Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID'));
        $taxCalculator = $taxManager->getTaxCalculator();

        return $taxCalculator->getTotalRate();
    }

    /**
     * Returns the carrier tax rate
     *
     * @param int $idCarrier
     * @param int|null $idAddress
     *
     * @return float $tax_rate
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getCarrierTaxRate($idCarrier, $idAddress = null)
    {
        $address = Address::initialize($idAddress);
        $idTaxRules = (int) Carrier::getIdTaxRulesGroupByIdCarrier((int) $idCarrier);

        $taxManager = TaxManagerFactory::getManager($address, $idTaxRules);
        $taxCalculator = $taxManager->getTaxCalculator();

        return $taxCalculator->getTotalRate();
    }

    /**
     * Returns the product tax
     *
     * @param int $idProduct
     * @param int|null $idAddress
     * @param Context|null $context
     *
     * @return float
     *
     * @throws PrestaShopException
     */
    public static function getProductTaxRate($idProduct, $idAddress = null, ?Context $context = null)
    {
        if ($context == null) {
            $context = Context::getContext();
        }

        $address = Address::initialize($idAddress);
        $idTaxRules = (int) Product::getIdTaxRulesGroupByIdProduct($idProduct, $context);

        $taxManager = TaxManagerFactory::getManager($address, $idTaxRules);
        $taxCalculator = $taxManager->getTaxCalculator();

        return $taxCalculator->getTotalRate();
    }

    /**
     * Returns tax name
     *
     * @param int $languageId
     *
     * @return string
     * @throws PrestaShopException
     */
    public function getName(int $languageId = 0)
    {
        if (is_array($this->name)) {
            if (isset($this->name[$languageId])) {
                return (string)$this->name[$languageId];
            }
            $defaultLangId = (int)Configuration::get('PS_LANG_DEFAULT');
            if (isset($this->name[$defaultLangId])) {
                return (string)$this->name[$languageId];
            }
            foreach ($this->name as $name) {
                return $name;
            }
            return '';
        } else {
            return (string)$this->name;
        }
    }
}
