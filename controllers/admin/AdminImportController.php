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

use Thirtybees\Core\Import\CSVDataSource;
use Thirtybees\Core\Import\DataSourceInterface;
use Thirtybees\Core\Import\ImportEntityType;

/**
 * Class AdminImportControllerCore
 */
class AdminImportControllerCore extends AdminController
{
    const MAX_COLUMNS = 6;
    const UNFRIENDLY_ERROR = false;
    const MAX_LINE_SIZE = 0;

    const ENTITY_TYPE_CATEGORIES = 'categories';
    const ENTITY_TYPE_PRODUCTS = 'products';
    const ENTITY_TYPE_COMBINATIONS = 'combinations';
    const ENTITY_TYPE_CUSTOMERS = 'customers';
    const ENTITY_TYPE_ADDRESSES = 'addresses';
    const ENTITY_TYPE_MANUFACTURERS = 'manufacturers';
    const ENTITY_TYPE_SUPPLIERS = 'suppliers';
    const ENTITY_TYPE_ALIAS = 'alias';
    const ENTITY_TYPE_STORE_CONTACTS = 'store_contacts';
    const ENTITY_TYPE_SUPPLY_ORDERS = 'supply_orders';
    const ENTITY_TYPE_SUPPLY_ORDER_DETAILS = 'supply_order_details';

    /** @var array $columnMask */
    public static $columnMask;
    /** @var array $defaultValues */
    public static $defaultValues = [];
    /** @var callable[] $validators */
    public static $validators;
    /** @var array $entitities */
    public $entities = [];
    /** @var array $available_fields */
    public $available_fields = [];
    /** @var array $required_fields */
    public $required_fields = [];
    /** @var string $separator */
    public $separator;
    /** @var string $multiple_value_separator */
    public $multiple_value_separator;

    /**
     * Cached information returned by hook 'actionRegisterImportDataSource'
     *
     * @var array
     */
    protected $registeredDataSources = null;

    /**
     * AdminImportControllerCore constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        @ini_set('max_execution_time', 0);

        $this->bootstrap = true;

        parent::__construct();

        $this->entities = [
            static::ENTITY_TYPE_CATEGORIES => $this->l('Categories'),
            static::ENTITY_TYPE_PRODUCTS => $this->l('Products'),
            static::ENTITY_TYPE_COMBINATIONS => $this->l('Combinations'),
            static::ENTITY_TYPE_CUSTOMERS => $this->l('Customers'),
            static::ENTITY_TYPE_ADDRESSES => $this->l('Addresses'),
            static::ENTITY_TYPE_MANUFACTURERS => $this->l('Manufacturers'),
            static::ENTITY_TYPE_SUPPLIERS => $this->l('Suppliers'),
            static::ENTITY_TYPE_ALIAS => $this->l('Alias'),
            static::ENTITY_TYPE_STORE_CONTACTS => $this->l('Store contacts'),
        ];

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
            $this->entities[static::ENTITY_TYPE_SUPPLY_ORDERS] = $this->l('Supply Orders');
            $this->entities[static::ENTITY_TYPE_SUPPLY_ORDER_DETAILS] = $this->l('Supply Order Details');
        }

        // Register module entity types
        foreach (static::getEntityTypes() as $key => $entityTypes) {
            $this->entities[$key] = $entityTypes->getName();
        }

        $this->separator = substr(trim(Tools::getValue('separator', ',')), 0, 1);
        $this->multiple_value_separator = substr(trim(Tools::getValue('multiple_value_separator', ';')), 0, 1);

        // initialize selected entity
        $selectedEntity = $this->getSelectedEntity();
        if (static::hasEntityType($selectedEntity)) {
            $this->available_fields = static::getEntityType($selectedEntity)->getAvailableFields();
            return;
        }

        // initialize default validators
        static::$validators = [
            'active'                 => [static::class, 'getBoolean'],
            'tax_rate'               => [static::class, 'getPrice'],
            'price_tex'              => [static::class, 'getPrice'],
            'price_tin'              => [static::class, 'getPrice'],
            'reduction_price'        => [static::class, 'getPrice'],
            'reduction_percent'      => [static::class, 'getPrice'],
            'wholesale_price'        => [static::class, 'getPrice'],
            'ecotax'                 => [static::class, 'getPrice'],
            'name'                   => [static::class, 'createMultiLangField'],
            'description'            => [static::class, 'createMultiLangField'],
            'additional_description' => [static::class, 'createMultiLangField'],
            'description_short'      => [static::class, 'createMultiLangField'],
            'meta_title'             => [static::class, 'createMultiLangField'],
            'meta_keywords'          => [static::class, 'createMultiLangField'],
            'meta_description'       => [static::class, 'createMultiLangField'],
            'link_rewrite'           => [static::class, 'createMultiLangField'],
            'available_now'          => [static::class, 'createMultiLangField'],
            'available_later'        => [static::class, 'createMultiLangField'],
            'category'               => [static::class, 'split'],
            'online_only'            => [static::class, 'getBoolean'],
            'accessories'            => [static::class, 'split'],
            'image_alt'              => [static::class, 'split'],
        ];

        switch ($selectedEntity) {
            case static::ENTITY_TYPE_COMBINATIONS:
                $this->required_fields = [
                    'group',
                    'attribute',
                ];

                $this->available_fields = [
                    'no'                        => ['label' => $this->l('Ignore this column')],
                    'id_product'                => ['label' => $this->l('Product ID')],
                    'product_reference'         => ['label' => $this->l('Product Reference')],
                    'group'                     => [
                        'label' => $this->l('Attribute (Name:Type:Position)').'*',
                    ],
                    'attribute'                 => [
                        'label' => $this->l('Value (Value:Position)').'*',
                    ],
                    'supplier_reference'        => ['label' => $this->l('Supplier reference')],
                    'reference'                 => ['label' => $this->l('Reference')],
                    'ean13'                     => ['label' => $this->l('EAN13')],
                    'upc'                       => ['label' => $this->l('UPC')],
                    'wholesale_price'           => ['label' => $this->l('Cost price')],
                    'price'                     => ['label' => $this->l('Impact on price')],
                    'ecotax'                    => ['label' => $this->l('Ecotax')],
                    'quantity'                  => ['label' => $this->l('Quantity')],
                    'minimal_quantity'          => ['label' => $this->l('Minimal quantity')],
                    'weight'                    => ['label' => $this->l('Impact on weight')],
                    'default_on'                => ['label' => $this->l('Default (0 = No, 1 = Yes)')],
                    'available_date'            => ['label' => $this->l('Combination availability date')],
                    'image_position'            => [
                        'label' => $this->l('Choose among product images by position (1,2,3...)'),
                    ],
                    'image_url'                 => ['label' => $this->l('Image URLs (x,y,z...)')],
                    'image_alt'                 => ['label' => $this->l('Image alt texts (x,y,z...)')],
                    'shop'                      => [
                        'label' => $this->l('ID / Name of shop'),
                        'help'  => $this->l('Ignore this field if you don\'t use the Multistore tool. If you leave this field empty, the default shop will be used.'),
                    ],
                    'advanced_stock_management' => [
                        'label' => $this->l('Advanced Stock Management'),
                        'help'  => $this->l('Enable Advanced Stock Management on product (0 = No, 1 = Yes)'),
                    ],
                    'depends_on_stock'          => [
                        'label' => $this->l('Depends on stock'),
                        'help'  => $this->l('0 = Use quantity set in product, 1 = Use quantity from warehouse.'),
                    ],
                    'warehouse'                 => [
                        'label' => $this->l('Warehouse'),
                        'help'  => $this->l('ID of the warehouse to set as storage.'),
                    ],
                    'width'                     => ['label' => $this->l('Impact on width')],
                    'height'                    => ['label' => $this->l('Impact on height')],
                    'depth'                     => ['label' => $this->l('Impact on depth')],
                ];

                static::$defaultValues = [
                    'reference'                 => '',
                    'supplier_reference'        => '',
                    'ean13'                     => '',
                    'upc'                       => '',
                    'wholesale_price'           => 0,
                    'price'                     => 0,
                    'ecotax'                    => 0,
                    'quantity'                  => 0,
                    'minimal_quantity'          => 1,
                    'weight'                    => 0,
                    'width'                     => 0,
                    'height'                    => 0,
                    'depth'                     => 0,
                    'default_on'                => 0,
                    'advanced_stock_management' => 0,
                    'depends_on_stock'          => 0,
                    'available_date'            => date('Y-m-d'),
                ];
                break;

            case static::ENTITY_TYPE_CATEGORIES:
                $this->available_fields = [
                    'no'               => ['label' => $this->l('Ignore this column')],
                    'id'               => ['label' => $this->l('ID')],
                    'active'           => ['label' => $this->l('Active (0/1)')],
                    'name'             => ['label' => $this->l('Name')],
                    'parent'           => ['label' => $this->l('Parent category')],
                    'is_root_category' => [
                        'label' => $this->l('Root category (0/1)'),
                        'help'  => $this->l('A category root is where a category tree can begin. This is used with multistore.'),
                    ],
                    'description'      => ['label' => $this->l('Description')],
                    'meta_title'       => ['label' => $this->l('Meta title')],
                    'meta_keywords'    => ['label' => $this->l('Meta keywords')],
                    'meta_description' => ['label' => $this->l('Meta description')],
                    'link_rewrite'     => ['label' => $this->l('Rewritten URL')],
                    'image'            => ['label' => $this->l('Image URL')],
                    'shop'             => [
                        'label' => $this->l('ID / Name of shop'),
                        'help'  => $this->l('Ignore this field if you don\'t use the Multistore tool. If you leave this field empty, the default shop will be used.'),
                    ],
                    'additional_description' => ['label' => $this->l('Additional description')],
                ];

                static::$defaultValues = [
                    'active'       => '1',
                    'parent'       => Configuration::get('PS_HOME_CATEGORY'),
                    'link_rewrite' => '',
                ];
                break;

            case static::ENTITY_TYPE_PRODUCTS:
                static::$validators['image'] = [ static::class, 'split' ];

                $this->available_fields = [
                    'no'                        => ['label' => $this->l('Ignore this column')],
                    'id'                        => ['label' => $this->l('ID')],
                    'active'                    => ['label' => $this->l('Active (0/1)')],
                    'name'                      => ['label' => $this->l('Name')],
                    'category'                  => ['label' => $this->l('Categories (x,y,z...)')],
                    'price_tex'                 => ['label' => $this->l('Price tax excluded')],
                    'price_tin'                 => ['label' => $this->l('Price tax included')],
                    'id_tax_rules_group'        => ['label' => $this->l('Tax rule ID')],
                    'wholesale_price'           => ['label' => $this->l('Cost price')],
                    'on_sale'                   => ['label' => $this->l('On sale (0/1)')],
                    'reduction_price'           => ['label' => $this->l('Discount amount')],
                    'reduction_percent'         => ['label' => $this->l('Discount percent')],
                    'reduction_from'            => ['label' => $this->l('Discount from')],
                    'reduction_to'              => ['label' => $this->l('Discount to')],
                    'reference'                 => ['label' => $this->l('Reference #')],
                    'supplier_reference'        => ['label' => $this->l('Supplier reference #')],
                    'supplier'                  => ['label' => $this->l('Supplier')],
                    'manufacturer'              => ['label' => $this->l('Brand')],
                    'ean13'                     => ['label' => $this->l('EAN13')],
                    'upc'                       => ['label' => $this->l('UPC')],
                    'ecotax'                    => ['label' => $this->l('Ecotax')],
                    'width'                     => ['label' => $this->l('Width')],
                    'height'                    => ['label' => $this->l('Height')],
                    'depth'                     => ['label' => $this->l('Depth')],
                    'weight'                    => ['label' => $this->l('Weight')],
                    'quantity'                  => ['label' => $this->l('Quantity')],
                    'minimal_quantity'          => ['label' => $this->l('Minimal quantity')],
                    'visibility'                => ['label' => $this->l('Visibility')],
                    'additional_shipping_cost'  => ['label' => $this->l('Additional shipping cost')],
                    'unity'                     => ['label' => $this->l('Unit for the price per unit')],
                    'unit_price'                => ['label' => $this->l('Price per unit')],
                    'description_short'         => ['label' => $this->l('Summary')],
                    'description'               => ['label' => $this->l('Description')],
                    'tags'                      => ['label' => $this->l('Tags (x,y,z...)')],
                    'meta_title'                => ['label' => $this->l('Meta title')],
                    'meta_keywords'             => ['label' => $this->l('Meta keywords')],
                    'meta_description'          => ['label' => $this->l('Meta description')],
                    'link_rewrite'              => ['label' => $this->l('Rewritten URL')],
                    'available_now'             => ['label' => $this->l('Label when in stock')],
                    'available_later'           => ['label' => $this->l('Label when backorder allowed')],
                    'available_for_order'       => ['label' => $this->l('Available for order (0 = No, 1 = Yes)')],
                    'available_date'            => ['label' => $this->l('Product availability date')],
                    'date_add'                  => ['label' => $this->l('Product creation date')],
                    'show_price'                => ['label' => $this->l('Show price (0 = No, 1 = Yes)')],
                    'image'                     => ['label' => $this->l('Image URLs (x,y,z...)')],
                    'image_alt'                 => ['label' => $this->l('Image alt texts (x,y,z...)')],
                    'delete_existing_images'    => [
                        'label' => $this->l('Delete existing images (0 = No, 1 = Yes)'),
                    ],
                    'features'                  => ['label' => $this->l('Feature (Name:Value:Position:Public name)')],
                    'online_only'               => ['label' => $this->l('Available online only (0 = No, 1 = Yes)')],
                    'condition'                 => ['label' => $this->l('Condition')],
                    'customizable'              => ['label' => $this->l('Customizable (0 = No, 1 = Yes)')],
                    'uploadable_files'          => ['label' => $this->l('Uploadable files (0 = No, 1 = Yes)')],
                    'text_fields'               => ['label' => $this->l('Text fields (0 = No, 1 = Yes)')],
                    'out_of_stock'              => ['label' => $this->l('Action when out of stock')],
                    'is_virtual'                => ['label' => $this->l('Virtual product (0 = No, 1 = Yes)')],
                    'file_url'                  => ['label' => $this->l('File URL')],
                    'nb_downloadable'           => [
                        'label' => $this->l('Number of allowed downloads'),
                        'help'  => $this->l('Number of days this file can be accessed by customers. Set to zero for unlimited access.'),
                    ],
                    'date_expiration'           => ['label' => $this->l('Expiration date')],
                    'nb_days_accessible'        => [
                        'label' => $this->l('Number of days'),
                        'help'  => $this->l('Number of days this file can be accessed by customers. Set to zero for unlimited access.'),
                    ],
                    'shop'                      => [
                        'label' => $this->l('ID / Name of shop'),
                        'help'  => $this->l('Ignore this field if you don\'t use the Multistore tool. If you leave this field empty, the default shop will be used.'),
                    ],
                    'advanced_stock_management' => [
                        'label' => $this->l('Advanced Stock Management'),
                        'help'  => $this->l('Enable Advanced Stock Management on product (0 = No, 1 = Yes).'),
                    ],
                    'depends_on_stock'          => [
                        'label' => $this->l('Depends on stock'),
                        'help'  => $this->l('0 = Use quantity set in product, 1 = Use quantity from warehouse.'),
                    ],
                    'warehouse'                 => [
                        'label' => $this->l('Warehouse'),
                        'help'  => $this->l('ID of the warehouse to set as storage.'),
                    ],
                    'accessories'               => ['label' => $this->l('Accessories (x,y,z...)')],
                ];

                static::$defaultValues = [
                    'id_category'               => [(int) Configuration::get('PS_HOME_CATEGORY')],
                    'id_category_default'       => null,
                    'active'                    => '1',
                    'width'                     => 0.000000,
                    'height'                    => 0.000000,
                    'depth'                     => 0.000000,
                    'weight'                    => 0.000000,
                    'visibility'                => 'both',
                    'additional_shipping_cost'  => 0.00,
                    'unit_price'                => 0,
                    'quantity'                  => 0,
                    'minimal_quantity'          => 1,
                    'price'                     => 0,
                    'id_tax_rules_group'        => 0,
                    'description_short'         => [(int) Configuration::get('PS_LANG_DEFAULT') => ''],
                    'link_rewrite'              => [(int) Configuration::get('PS_LANG_DEFAULT') => ''],
                    'online_only'               => 0,
                    'condition'                 => 'new',
                    'available_date'            => date('Y-m-d'),
                    'date_add'                  => date('Y-m-d H:i:s'),
                    'date_upd'                  => date('Y-m-d H:i:s'),
                    'customizable'              => 0,
                    'uploadable_files'          => 0,
                    'text_fields'               => 0,
                    'advanced_stock_management' => 0,
                    'depends_on_stock'          => 0,
                    'is_virtual'                => 0,
                ];
                break;

            case static::ENTITY_TYPE_CUSTOMERS:
                //Overwrite required_fields AS only email is required whereas other entities
                $this->required_fields = ['email', 'passwd', 'lastname', 'firstname'];

                $this->available_fields = [
                    'no'               => ['label' => $this->l('Ignore this column')],
                    'id'               => ['label' => $this->l('ID')],
                    'active'           => ['label' => $this->l('Active  (0/1)')],
                    'id_gender'        => ['label' => $this->l('Titles ID (Mr = 1, Ms = 2, else 0)')],
                    'email'            => ['label' => $this->l('Email').'*'],
                    'passwd'           => ['label' => $this->l('Password').'*'],
                    'birthday'         => ['label' => $this->l('Birth date')],
                    'lastname'         => ['label' => $this->l('Last name').'*'],
                    'firstname'        => ['label' => $this->l('First name').'*'],
                    'newsletter'       => ['label' => $this->l('Newsletter (0/1)')],
                    'optin'            => ['label' => $this->l('Partner offers (0/1)')],
                    'date_add'         => ['label' => $this->l('Registration date')],
                    'group'            => ['label' => $this->l('Groups (x,y,z...)')],
                    'id_default_group' => ['label' => $this->l('Default group ID')],
                    'id_shop'          => [
                        'label' => $this->l('ID / Name of shop'),
                        'help'  => $this->l('Ignore this field if you don\'t use the Multistore tool. If you leave this field empty, the default shop will be used.'),
                    ],
                ];

                static::$defaultValues = [
                    'active'  => '1',
                    'id_shop' => Configuration::get('PS_SHOP_DEFAULT'),
                ];
                break;

            case static::ENTITY_TYPE_ADDRESSES:
                //Overwrite required_fields
                $this->required_fields = [
                    'alias',
                    'lastname',
                    'firstname',
                    'address1',
                    'postcode',
                    'country',
                    'customer_email',
                    'city',
                ];

                $this->available_fields = [
                    'no'             => ['label' => $this->l('Ignore this column')],
                    'id'             => ['label' => $this->l('ID')],
                    'alias'          => ['label' => $this->l('Alias').'*'],
                    'active'         => ['label' => $this->l('Active  (0/1)')],
                    'customer_email' => ['label' => $this->l('Customer email').'*'],
                    'id_customer'    => ['label' => $this->l('Customer ID')],
                    'manufacturer'   => ['label' => $this->l('Brand')],
                    'supplier'       => ['label' => $this->l('Supplier')],
                    'company'        => ['label' => $this->l('Company')],
                    'lastname'       => ['label' => $this->l('Last name').'*'],
                    'firstname'      => ['label' => $this->l('First name ').'*'],
                    'address1'       => ['label' => $this->l('Address').'*'],
                    'address2'       => ['label' => $this->l('Address (2)')],
                    'postcode'       => ['label' => $this->l('Zip/postal code').'*'],
                    'city'           => ['label' => $this->l('City').'*'],
                    'country'        => ['label' => $this->l('Country').'*'],
                    'state'          => ['label' => $this->l('State')],
                    'other'          => ['label' => $this->l('Other')],
                    'phone'          => ['label' => $this->l('Phone')],
                    'phone_mobile'   => ['label' => $this->l('Mobile Phone')],
                    'vat_number'     => ['label' => $this->l('VAT number')],
                    'dni'            => ['label' => $this->l('Identification number')],
                ];

                static::$defaultValues = [
                    'alias'    => 'Alias',
                    'postcode' => 'X',
                ];
                break;
            case static::ENTITY_TYPE_MANUFACTURERS:
            case static::ENTITY_TYPE_SUPPLIERS:
                //Overwrite validators AS name is not MultiLangField
                static::$validators = [
                    'description'       => [static::class, 'createMultiLangField'],
                    'short_description' => [static::class, 'createMultiLangField'],
                    'meta_title'        => [static::class, 'createMultiLangField'],
                    'meta_keywords'     => [static::class, 'createMultiLangField'],
                    'meta_description'  => [static::class, 'createMultiLangField'],
                ];

                $this->available_fields = [
                    'no'                => ['label' => $this->l('Ignore this column')],
                    'id'                => ['label' => $this->l('ID')],
                    'active'            => ['label' => $this->l('Active (0/1)')],
                    'name'              => ['label' => $this->l('Name')],
                    'description'       => ['label' => $this->l('Description')],
                    'short_description' => ['label' => $this->l('Short description')],
                    'meta_title'        => ['label' => $this->l('Meta title')],
                    'meta_keywords'     => ['label' => $this->l('Meta keywords')],
                    'meta_description'  => ['label' => $this->l('Meta description')],
                    'image'             => ['label' => $this->l('Image URL')],
                    'shop'              => [
                        'label' => $this->l('ID / Name of group shop'),
                        'help'  => $this->l('Ignore this field if you don\'t use the Multistore tool. If you leave this field empty, the default shop will be used.'),
                    ],
                ];

                static::$defaultValues = [
                    'shop' => Shop::getGroupFromShop(Configuration::get('PS_SHOP_DEFAULT')),
                ];
                break;
            case static::ENTITY_TYPE_ALIAS:
                //Overwrite required_fields
                $this->required_fields = [
                    'alias',
                    'search',
                ];
                $this->available_fields = [
                    'no'     => ['label' => $this->l('Ignore this column')],
                    'id'     => ['label' => $this->l('ID')],
                    'alias'  => ['label' => $this->l('Alias').'*'],
                    'search' => ['label' => $this->l('Search').'*'],
                    'active' => ['label' => $this->l('Active')],
                ];
                static::$defaultValues = [
                    'active' => '1',
                ];
                break;
            case static::ENTITY_TYPE_STORE_CONTACTS:
                // Overwrite validators
                static::$validators = [
                    'hours' => [static::class, 'split'],
                ];
                $this->required_fields = [
                    'address1',
                    'city',
                    'country',
                    'latitude',
                    'longitude',
                ];
                $this->available_fields = [
                    'no'        => ['label' => $this->l('Ignore this column')],
                    'id'        => ['label' => $this->l('ID')],
                    'active'    => ['label' => $this->l('Active (0/1)')],
                    'name'      => ['label' => $this->l('Name')],
                    'address1'  => ['label' => $this->l('Address').'*'],
                    'address2'  => ['label' => $this->l('Address (2)')],
                    'postcode'  => ['label' => $this->l('Zip/postal code')],
                    'state'     => ['label' => $this->l('State')],
                    'city'      => ['label' => $this->l('City').'*'],
                    'country'   => ['label' => $this->l('Country').'*'],
                    'latitude'  => ['label' => $this->l('Latitude').'*'],
                    'longitude' => ['label' => $this->l('Longitude').'*'],
                    'phone'     => ['label' => $this->l('Phone')],
                    'fax'       => ['label' => $this->l('Fax')],
                    'email'     => ['label' => $this->l('Email address')],
                    'note'      => ['label' => $this->l('Note')],
                    'hours'     => ['label' => $this->l('Hours (x;y;z...)')],
                    'image'     => ['label' => $this->l('Image URL')],
                    'shop'      => [
                        'label' => $this->l('ID / Name of shop'),
                        'help'  => $this->l('Ignore this field if you don\'t use the Multistore tool. If you leave this field empty, the default shop will be used.'),
                    ],
                ];
                static::$defaultValues = [
                    'active' => '1',
                ];
                break;
            case static::ENTITY_TYPE_SUPPLY_ORDERS:
                // required fields
                $this->required_fields = [
                    'id_supplier',
                    'id_warehouse',
                    'reference',
                    'date_delivery_expected',
                ];
                // available fields
                $this->available_fields = [
                    'no'                     => ['label' => $this->l('Ignore this column')],
                    'id'                     => ['label' => $this->l('ID')],
                    'id_supplier'            => ['label' => $this->l('Supplier ID *')],
                    'id_lang'                => ['label' => $this->l('Lang ID')],
                    'id_warehouse'           => ['label' => $this->l('Warehouse ID *')],
                    'id_currency'            => ['label' => $this->l('Currency ID *')],
                    'reference'              => ['label' => $this->l('Supply Order Reference *')],
                    'date_delivery_expected' => ['label' => $this->l('Delivery Date (Y-M-D)*')],
                    'discount_rate'          => ['label' => $this->l('Discount rate')],
                    'is_template'            => ['label' => $this->l('Template')],
                ];
                // default values
                static::$defaultValues = [
                    'id_lang'       => (int) Configuration::get('PS_LANG_DEFAULT'),
                    'id_currency'   => Currency::getDefaultCurrency()->id,
                    'discount_rate' => '0',
                    'is_template'   => '0',
                ];
                break;
            case static::ENTITY_TYPE_SUPPLY_ORDER_DETAILS:
                // required fields
                $this->required_fields = [
                    'supply_order_reference',
                    'id_product',
                    'unit_price_te',
                    'quantity_expected',
                ];
                // available fields
                $this->available_fields = [
                    'no'                     => ['label' => $this->l('Ignore this column')],
                    'supply_order_reference' => ['label' => $this->l('Supply Order Reference *')],
                    'id_product'             => ['label' => $this->l('Product ID *')],
                    'id_product_attribute'   => ['label' => $this->l('Product Attribute ID')],
                    'unit_price_te'          => ['label' => $this->l('Unit Price (tax excl.)*')],
                    'quantity_expected'      => ['label' => $this->l('Quantity Expected *')],
                    'discount_rate'          => ['label' => $this->l('Discount Rate')],
                    'tax_rate'               => ['label' => $this->l('Tax Rate')],
                ];
                // default values
                static::$defaultValues = [
                    'discount_rate' => '0',
                    'tax_rate'      => '0',
                ];
                break;
        }
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    protected static function getBoolean($field)
    {
        return (bool) $field;
    }

    /**
     * @param string $field
     *
     * @return float
     */
    protected static function getPrice($field)
    {
        return Tools::parseNumber($field);
    }

    /**
     * @param string $infos
     * @param string $key
     * @param ObjectModel $entity
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    protected static function fillInfo($infos, $key, $entity)
    {
        $infos = trim($infos);
        if (isset(static::$validators[$key][1]) && static::$validators[$key][1] == 'createMultiLangField' && Tools::getValue('iso_lang')) {
            $idLang = Language::getIdByIso(Tools::getValue('iso_lang'));
            $tmp = call_user_func(static::$validators[$key], $infos);
            foreach ($tmp as $idLangTmp => $value) {
                if (empty($entity->{$key}[$idLangTmp]) || $idLangTmp == $idLang) {
                    $entity->{$key}[$idLangTmp] = $value;
                }
            }
        } elseif (!empty($infos) || $infos == '0') { // ($infos == '0') => if you want to disable a product by using "0" in active because empty('0') return true
            $entity->{$key} = isset(static::$validators[$key]) ? call_user_func(static::$validators[$key], $infos) : $infos;
        }

        return true;
    }

    /**
     * @param string $a
     * @param string $b
     *
     * @return int
     */
    protected static function usortFiles($a, $b)
    {
        if ($a == $b) {
            return 0;
        }

        return ($b < $a) ? 1 : -1;
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        $backOfficeTheme = ((Validate::isLoadedObject($this->context->employee)
            && $this->context->employee->bo_theme) ? $this->context->employee->bo_theme : 'default');

        if (!file_exists(_PS_BO_ALL_THEMES_DIR_.$backOfficeTheme.DIRECTORY_SEPARATOR.'template')) {
            $backOfficeTheme = 'default';
        }

        // We need to set parent media first, so that jQuery is loaded before the dependant plugins
        parent::setMedia();

        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$backOfficeTheme.'/js/jquery.iframe-transport.js');
        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$backOfficeTheme.'/js/jquery.fileupload.js');
        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$backOfficeTheme.'/js/jquery.fileupload-process.js');
        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$backOfficeTheme.'/js/jquery.fileupload-validate.js');
        $this->addJs(__PS_BASE_URI__.'js/vendor/spin.js');
        $this->addJs(__PS_BASE_URI__.'js/vendor/ladda.js');
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     * @noinspection PhpUnused
     */
    public function ajaxProcessuploadCsv()
    {
        $filenamePrefix = date('YmdHis').'-';
        $filename = preg_replace('/[^A-Za-z0-9._\-]/', '', $_FILES['file']['name']);
        $extensions = array_keys($this->getFileExtensions());
        $extensionsRegexp = implode('|', array_map('preg_quote', $extensions));

        if (isset($_FILES['file']) && !empty($_FILES['file']['error'])) {
            $_FILES['file']['error'] = Tools::decodeUploadError($_FILES['file']['error']);
        } elseif (!preg_match('#([^.]*?)\.('.$extensionsRegexp.')$#is', $filename)) {
            $_FILES['file']['error'] = $this->l('Unsupported file type. Supported extensions: ') . implode(', ' , $extensions);
        } elseif (!@filemtime($_FILES['file']['tmp_name']) ||
            !@move_uploaded_file($_FILES['file']['tmp_name'], static::getPath().$filenamePrefix.str_replace("\0", '', $filename))
        ) {
            $_FILES['file']['error'] = $this->l('An error occurred while uploading / copying the file.');
        } else {
            @chmod(static::getPath().$filenamePrefix.$filename, 0664);
            $_FILES['file']['filename'] = $filenamePrefix.str_replace('\0', '', $filename);
        }

        $this->ajaxDie(json_encode($_FILES));
    }

    /**
     * @param string $file
     *
     * @return string
     */
    public static function getPath($file = '')
    {
        return _PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.'import'.DIRECTORY_SEPARATOR.$file;
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    public function init()
    {
        parent::init();
        if (Tools::isSubmit('submitImportFile')) {
            $this->display = 'import';
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function initContent()
    {
        $this->initToolbar();
        $this->initPageHeaderToolbar();
        if ($this->display == 'import') {
            if (Tools::getValue('filename')) {
                $this->content .= $this->renderView();
            } else {
                $this->errors[] = $this->l('To proceed, please upload a file first.');
                $this->content .= $this->renderForm();
            }
        } else {
            $this->content .= $this->renderForm();
        }

        $this->context->smarty->assign(
            [
                'content'                   => $this->content,
                'url_post'                  => static::$currentIndex.'&token='.$this->token,
                'show_page_header_toolbar'  => $this->show_page_header_toolbar,
                'page_header_toolbar_title' => $this->page_header_toolbar_title,
                'page_header_toolbar_btn'   => $this->page_header_toolbar_btn,
            ]
        );
    }

    /**
     * @return void
     */
    public function initToolbar()
    {
        if ($this->display === 'import') {
            // Default cancel button - like old back link
            $back = Tools::safeOutput(Tools::getValue('back', ''));
            if (empty($back)) {
                $back = static::$currentIndex . '&token=' . $this->token;
            }

            $this->toolbar_btn['cancel'] = [
                'href' => $back,
                'desc' => $this->l('Cancel'),
            ];
            // Default save button - action dynamically handled in javascript
            $this->toolbar_btn['save-import'] = [
                'href' => '#',
                'desc' => $this->l('Import .CSV data'),
            ];
        }
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderView()
    {
        $this->addJS(_PS_JS_DIR_.'admin/import.js');

        $datasource = $this->openDataSource();

        $nbColumn = $datasource->getNumberOfColumns();
        $nbTable = ceil($nbColumn / static::MAX_COLUMNS);

        $res = [];
        foreach ($this->required_fields as $elem) {
            $res[] = '\''.$elem.'\'';
        }

        $previewRows = [];
        for ($i = 0; $i<10; $i++) {
            $previewRow = $datasource->getRow();
            if ($previewRow) {
                $previewRows[] = $previewRow;
            }
        }
        $data = [];
        for ($i = 0; $i < $nbTable; $i++) {
            $data[$i] = $this->generateContentTable($i, $nbColumn, $previewRows);
        }

        $entityType = $this->getSelectedEntity();

        $this->context->cookie->isoLangSelected = urlencode(Tools::getValue('iso_lang'));
        $this->context->cookie->separatorSelected = urlencode($this->separator);
        $this->context->cookie->multipleValueSeparatorSelected = urlencode($this->multiple_value_separator);
        $this->context->cookie->fileSelected = urlencode(Tools::getValue('filename'));

        // Show date format select only in Products,Combinations and Customer import
        $dateFormats = null;
        if (in_array(Tools::getIntValue('entity'), [1, 2, 3])) {
            if ( ! empty($this->context->language)
                && ! empty($this->context->language->date_format_lite)
            ) {
                $dateFormats[$this->context->language->date_format_lite] = [
                    'label' => $this->context->language->date_format_lite
                               .' - '
                               .$this->l('from back office language'),
                ];
            }
            $dateFormats['Y-m-d'] = ['label' => 'Y-m-d'];
            $dateFormats['Y-d-m'] = ['label' => 'Y-d-m'];
            $dateFormats['d-m-Y'] = ['label' => 'd-m-Y'];
            $dateFormats['d.m.Y'] = ['label' => 'd.m.Y'];
        }

        $this->tpl_view_vars = [
            'import_matchs'    => Db::readOnly()->getArray((new DbQuery())->select('*')->from('import_match')),
            'fields_value'     => [
                'filename'                 => Tools::getValue('filename'),
                'importer'                 => Tools::getValue('importer'),
                'entity'                   => $entityType,
                'iso_lang'                 => Tools::getValue('iso_lang'),
                'truncate'                 => Tools::getValue('truncate'),
                'forceIDs'                 => Tools::getValue('forceIDs'),
                'regenerate'               => Tools::getValue('regenerate'),
                'forceCat'                 => Tools::getValue('forceCat'),
                'match_ref'                => Tools::getValue('match_ref'),
                'only_file_product'        => Tools::getValue('only_file_product'),
                'separator'                => $this->separator,
                'multiple_value_separator' => $this->multiple_value_separator,
            ],
            'nb_table'         => $nbTable,
            'nb_column'        => $nbColumn,
            'res'              => implode(',', $res),
            'max_columns'      => static::MAX_COLUMNS,
            'no_pre_select'    => ['price_tin', 'feature'],
            'available_fields' => $this->available_fields,
            'data'             => $data,
            'date_formats'     => $dateFormats,
        ];

        return parent::renderView();
    }

    /**
     * @param int $offset
     * @return DataSourceInterface
     *
     * @throws PrestaShopException
     */
    protected function openDataSource($offset = 0)
    {
        // construct data source
        $filepath = static::getPath(Tools::getValue('filename'));
        $importer = Tools::getValue('importer');
        $implementations = $this->getRegisteredDataSources();
        if (! isset($implementations[$importer])) {
            throw new PrestaShopException('Import implementation "'.$importer.'" not found');
        }
        $implementation = $implementations[$importer];
        $constructor = $implementation['constructor'];

        /** @var DataSourceInterface $dataSource */
        $dataSource = $constructor($filepath, [
            'separator' => $this->separator,
            'multipleValueSeparator' => $this->multiple_value_separator,
        ]);
        if (! $dataSource instanceof DataSourceInterface) {
            throw new PrestaShopException('Failed to create datasource using implementation "'.$implementation['name'].'"');
        }

        // seek current row
        $toSkip = Tools::getIntValue('skip');
        if ($offset && $offset > 0) {
            $toSkip += $offset;
        }

        for ($i = 0; $i < $toSkip; ++$i) {
            $dataSource->getRow();
        }
        return $dataSource;
    }

    /**
     * @param string $currentTable
     * @param int $nbColumn
     * @param array $previewRows
     *
     * @return string
     */
    protected function generateContentTable($currentTable, $nbColumn, array $previewRows)
    {
        $html = '<table id="table'.$currentTable.'" style="display: none;" class="table table-bordered"><thead><tr>';
        // Header
        for ($i = 0; $i < $nbColumn; $i++) {
            if (static::MAX_COLUMNS * (int) $currentTable <= $i && $i < static::MAX_COLUMNS * ((int) $currentTable + 1)) {
                $html .= '<th>
							<select id="type_value['.$i.']"
								name="type_value['.$i.']"
								class="type_value">
								'.$this->getTypeValuesOptions($i).'
							</select>
						</th>';
            }
        }
        $html .= '</tr></thead><tbody>';

        static::setLocale();
        foreach ($previewRows as $currentLine => $line) {
            $html .= '<tr id="table_'.$currentTable.'_line_'.$currentLine.'">';
            foreach ($line as $nbC => $column) {
                $column = $column ?? '';
                if ((static::MAX_COLUMNS * (int) $currentTable <= $nbC) && ((int) $nbC < static::MAX_COLUMNS * ((int) $currentTable + 1))) {
                    $html .= '<td>'.htmlentities(mb_substr($column, 0, 200), ENT_QUOTES, 'UTF-8').'</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param int $nbC
     *
     * @return string
     */
    protected function getTypeValuesOptions($nbC)
    {
        $i = 0;
        $noPreSelect = ['price_tin', 'feature'];

        $options = '';

        foreach ($this->available_fields as $key => $field) {
            $options .= '<option value="'.$key.'"';
            if ($key === 'price_tin') {
                ++$nbC;
            }
            if ($i === ($nbC + 1) && (!in_array($key, $noPreSelect))) {
                $options .= ' selected="selected"';
            }
            $options .= '>'.$i.'. '.$field['label'].'</option>';
            ++$i;
        }

        return $options;
    }

    /**
     * @return void
     */
    public static function setLocale()
    {
        $isoLang = trim(Tools::getValue('iso_lang'));
        setlocale(LC_COLLATE, strtolower($isoLang).'_'.strtoupper($isoLang).'.UTF-8');
        setlocale(LC_CTYPE, strtolower($isoLang).'_'.strtoupper($isoLang).'.UTF-8');
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderForm()
    {
        if (!is_dir(static::getPath())) {
            return !($this->errors[] = $this->l('The import directory doesn\'t exist. Please check your file path.'));
        }

        if (!is_writable(static::getPath())) {
            $this->displayWarning($this->l('The import directory must be writable (CHMOD 755 / 777).'));
        }

        $extensions = $this->getFileExtensions();

        $filesToImport = scandir(static::getPath());
        uasort($filesToImport, [static::class, 'usortFiles']);
        foreach ($filesToImport as $k => &$filename) {
            if (is_dir(static::getPath().$filename)) {
                unset($filesToImport[$k]);
            } else {
                $info = pathinfo($filename);
                if (! array_key_exists($info['extension'], $extensions)) {
                    unset($filesToImport[$k]);
                }
            }
        }
        unset($filename);

        $this->fields_form = [''];

        $this->toolbar_scroll = false;
        $this->toolbar_btn = [];

        // adds fancybox
        $this->addJqueryPlugin(['fancybox']);


        $entitySelected = $this->getSelectedEntity();

        $fileSelected = '';
        if (isset($this->context->cookie->fileSelected) &&
            @filemtime(
                static::getPath(
                    urldecode($this->context->cookie->fileSelected)
                )
            )
        ) {
            $fileSelected = urldecode($this->context->cookie->fileSelected);
        } else {
            $this->context->cookie->fileSelected = $fileSelected;
        }

        $idLangSelected = '';
        if (isset($this->context->cookie->isoLangSelected) && $this->context->cookie->isoLangSelected) {
            $idLangSelected = (int) Language::getIdByIso(urldecode($this->context->cookie->isoLangSelected));
        }

        $separatorSelected = $this->separator;
        if (isset($this->context->cookie->separator_selected) && $this->context->cookie->separator_selected) {
            $separatorSelected = urldecode($this->context->cookie->separator_selected);
        }

        $multipleValueSeparatorSelected = $this->multiple_value_separator;
        if (isset($this->context->cookie->multipleValueSeparatorSelected) && $this->context->cookie->multipleValueSeparatorSelected) {
            $multipleValueSeparatorSelected = urldecode($this->context->cookie->multipleValueSeparatorSelected);
        }

        $importers = [];
        foreach ($this->getRegisteredDataSources() as $key => $definition) {
            $importers[$key] = $definition['name'];
        }

        $this->tpl_form_vars = [
            'post_max_size'                     => Tools::getMaxUploadSize(),
            'module_confirmation'               => Tools::isSubmit('import') && (isset($this->warnings) && !count($this->warnings)),
            'path_import'                       => static::getPath(),
            'entities'                          => $this->entities,
            'entity_selected'                   => $entitySelected,
            'truncatableEntities'               => $this->getTruncatableEntityTypes(),
            'file_selected'                     => $fileSelected,
            'acceptExtensions'                  => implode('|', array_keys($extensions)),
            'importers'                         => $importers,
            'importersExtensions'               => $extensions,
            'separator_selected'                => $separatorSelected,
            'multiple_value_separator_selected' => $multipleValueSeparatorSelected,
            'files_to_import'                   => $filesToImport,
            'languages'                         => Language::getLanguages(false),
            'id_language'                       => ($idLangSelected) ? $idLangSelected : $this->context->language->id,
            'available_fields'                  => $this->getAvailableFields(),
            'truncateAuthorized'                => (Shop::isFeatureActive() && $this->context->employee->isSuperAdmin()) || !Shop::isFeatureActive(),
            'PS_ADVANCED_STOCK_MANAGEMENT'      => Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT'),
        ];

        return parent::renderForm();
    }

    /**
     * @param bool $inArray
     *
     * @return array|string
     */
    public function getAvailableFields($inArray = false)
    {
        $i = 0;
        $fields = [];
        $keys = array_keys($this->available_fields);
        array_shift($keys);
        foreach ($this->available_fields as $k => $field) {
            if ($k === 'no') {
                continue;
            }
            if ($k === 'price_tin') { // Special case for Product : either one or the other. Not both.
                $fields[$i - 1] = '<div>'.$this->available_fields[$keys[$i - 1]]['label'].'<br/>&nbsp;&nbsp;<i>'.$this->l('or').'</i>&nbsp;&nbsp; '.$field['label'].'</div>';
            } else {
                if (isset($field['help'])) {
                    $html = '&nbsp;<a href="#" class="help-tooltip" data-toggle="tooltip" title="'.$field['help'].'"><i class="icon-info-sign"></i></a>';
                } else {
                    $html = '';
                }
                $fields[] = '<div>'.$field['label'].$html.'</div>';
            }
            ++$i;
        }
        if ($inArray) {
            return $fields;
        } else {
            return implode("\n\r", $fields);
        }
    }

    /**
     * @param int $defaultLanguageId
     * @param string $categoryName
     * @param int| null $idParentCategory
     *
     * @return void
     *
     * @throws PrestaShopException
     * @noinspection PhpUnused
     */
    public function productImportCreateCat($defaultLanguageId, $categoryName, $idParentCategory = null)
    {
        $categoryToCreate = new Category();
        $shopIsFeatureActive = Shop::isFeatureActive();
        if (!$shopIsFeatureActive) {
            $categoryToCreate->id_shop_default = 1;
        } else {
            $categoryToCreate->id_shop_default = (int) $this->context->shop->id;
        }
        $categoryToCreate->name = static::createMultiLangField(trim($categoryName));
        $categoryToCreate->active = 1;
        $categoryToCreate->id_parent = (int) $idParentCategory ? (int) $idParentCategory : (int) Configuration::get('PS_HOME_CATEGORY'); // Default parent is home for unknown category to create
        $categoryLinkRewrite = Tools::link_rewrite($categoryToCreate->name[$defaultLanguageId]);
        $categoryToCreate->link_rewrite = static::createMultiLangField($categoryLinkRewrite);

        if (($fieldError = $categoryToCreate->validateFields(static::UNFRIENDLY_ERROR, true)) !== true ||
            ($langFieldError = $categoryToCreate->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) !== true ||
            !$categoryToCreate->add()
        ) {
            $this->errors[] = sprintf(
                $this->l('%1$s (ID: %2$s) cannot be saved'),
                $categoryToCreate->name[$defaultLanguageId],
                (!empty($categoryToCreate->id)) ? $categoryToCreate->id : 'null'
            );
            if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
            }
        }
    }

    /**
     * @param mixed $field
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function createMultiLangField($field)
    {
        $res = [];
        foreach (Language::getIDs(false) as $idLang) {
            $res[$idLang] = $field;
        }

        return $res;
    }

    /**
     * @return bool|null
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        /* thirty bees demo mode */
        if (_PS_MODE_DEMO_) {
            $this->errors[] = $this->l('This functionality has been disabled.');

            return null;
        }

        if (Tools::isSubmit('import')) {
            $this->importByGroups();
        } elseif ($filename = Tools::getValue('csvfilename')) {
            $filename = urldecode($filename);
            $file = static::getPath(basename($filename));
            if (realpath(dirname($file)) != realpath(static::getPath())) {
                exit();
            }
            if (!empty($filename)) {
                $bName = basename($filename);
                if (Tools::getValue('delete') && file_exists($file)) {
                    @unlink($file);
                } elseif (file_exists($file)) {
                    $bName = explode('.', $bName);
                    $bName = strtolower($bName[count($bName) - 1]);
                    $mimeTypes = ['csv' => 'text/csv'];

                    $mimeType = $mimeTypes[$bName] ?? 'application/octet-stream';

                    if (ob_get_level() && ob_get_length() > 0) {
                        ob_end_clean();
                    }

                    header('Content-Transfer-Encoding: binary');
                    header('Content-Type: '.$mimeType);
                    header('Content-Length: '.filesize($file));
                    header('Content-Disposition: attachment; filename="'.$filename.'"');
                    $fp = fopen($file, 'rb');
                    while (is_resource($fp) && !feof($fp)) {
                        echo fgets($fp, 16384);
                    }
                    exit;
                }
            }
        }
        return parent::postProcess();
    }

    /**
     * @param int|bool $offset
     * @param int|bool $limit
     * @param array|null $results
     * @param bool $validateOnly
     * @param int $moreStep
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    public function importByGroups($offset = false, $limit = false, &$results = null, $validateOnly = false, $moreStep = 0)
    {
        // Check if the CSV file exist
        if (Tools::getValue('filename')) {
            $entityType = $this->getSelectedEntity();
            $shopIsFeatureActive = Shop::isFeatureActive();
            // If i am a superadmin, i can truncate table (ONLY IF OFFSET == 0 or false and NOT FOR VALIDATION MODE!)
            if (!$offset && !$moreStep && !$validateOnly && (($shopIsFeatureActive && $this->context->employee->isSuperAdmin()) || !$shopIsFeatureActive) && Tools::getValue('truncate')) {
                $this->truncateTables($entityType);
            }
            $doneCount = 0;
            // Sometime, import will use registers to memorize data across all elements to import (for trees, or else).
            // Since import is splitted in multiple ajax calls, we must keep these data across all steps of the full import.
            $crossStepsVariables = [];
            if ($crossStepsVars = Tools::getValue('crossStepsVars')) {
                $crossStepsVars = json_decode($crossStepsVars, true);
                if (is_array($crossStepsVars)) {
                    $crossStepsVariables = $crossStepsVars;
                } else {
                    $this->warnings[] = $this->l('Failed to deserialize cross-requests variables');
                }
            }

            if (static::hasEntityType($entityType)) {
                $doneCount += $this->importGroup(static::getEntityType($entityType), $offset, $limit, $crossStepsVariables, $validateOnly, $moreStep);
            } else {
                // fallback to original implementation
                switch ($entityType) {
                    case static::ENTITY_TYPE_CATEGORIES:
                        $doneCount += $this->categoryImport($offset, $limit, $crossStepsVariables, $validateOnly);
                        $this->clearSmartyCache();
                        break;
                    case static::ENTITY_TYPE_PRODUCTS:
                        if (!defined('PS_MASS_PRODUCT_CREATION')) {
                            define('PS_MASS_PRODUCT_CREATION', true);
                        }
                        $moreStepLabels = [$this->l('Linking Accessories...')];
                        $doneCount += $this->productImport($offset, $limit, $crossStepsVariables, $validateOnly, $moreStep);
                        $this->clearSmartyCache();
                        break;
                    case static::ENTITY_TYPE_CUSTOMERS:
                        $doneCount += $this->customerImport($offset, $limit, $validateOnly);
                        break;
                    case static::ENTITY_TYPE_ADDRESSES:
                        $doneCount += $this->addressImport($offset, $limit, $validateOnly);
                        break;
                    case static::ENTITY_TYPE_COMBINATIONS:
                        $doneCount += $this->attributeImport($offset, $limit, $crossStepsVariables, $validateOnly);
                        $this->clearSmartyCache();
                        break;
                    case static::ENTITY_TYPE_MANUFACTURERS:
                        $doneCount += $this->manufacturerImport($offset, $limit, $validateOnly);
                        $this->clearSmartyCache();
                        break;
                    case static::ENTITY_TYPE_SUPPLIERS:
                        $doneCount += $this->supplierImport($offset, $limit, $validateOnly);
                        $this->clearSmartyCache();
                        break;
                    case static::ENTITY_TYPE_ALIAS:
                        $doneCount += $this->aliasImport($offset, $limit, $validateOnly);
                        break;
                    case static::ENTITY_TYPE_STORE_CONTACTS:
                        $doneCount += $this->storeContactImport($offset, $limit, $validateOnly);
                        $this->clearSmartyCache();
                        break;
                    case static::ENTITY_TYPE_SUPPLY_ORDERS:
                        $doneCount += $this->supplyOrdersImport($offset, $limit, $validateOnly);
                        break;
                    case static::ENTITY_TYPE_SUPPLY_ORDER_DETAILS:
                        $doneCount += $this->supplyOrdersDetailsImport($offset, $limit, $crossStepsVariables, $validateOnly);
                        break;
                }
            }

            if ($results !== null) {
                $results['isFinished'] = ($doneCount < $limit);
                $results['doneCount'] = $offset + $doneCount;
                if ($offset === 0) {
                    // compute total count only once, because it takes time
                    $datasource = $this->openDataSource(0);
                    $results['totalCount'] = $datasource->getNumberOfRows() - Tools::getIntValue('skip');
                    $datasource->close();
                }
                if (!isset($moreStepLabels)) {
                    $moreStepLabels = [];
                }
                if (!$results['isFinished'] || (!$validateOnly && ($moreStep < count($moreStepLabels)))) {
                    // Since we'll have to POST this array from ajax for the next call, we should care about it size.
                    $nextPostSize = mb_strlen(json_encode($crossStepsVariables));
                    $results['crossStepsVariables'] = $crossStepsVariables;
                    $results['nextPostSize'] = $nextPostSize + (1024 * 64); // 64KB more for the rest of the POST query.
                    $results['postSizeLimit'] = Tools::getMaxUploadSize();
                }
                if ($results['isFinished'] && !$validateOnly && ($moreStep < count($moreStepLabels))) {
                    $results['oneMoreStep'] = $moreStep + 1;
                    $results['moreStepLabel'] = $moreStepLabels[$moreStep];
                }
            }

            $logMessage = sprintf($this->l('%s import'), $entityType);
            if ($offset !== false && $limit !== false) {
                $logMessage .= ' '.sprintf($this->l('(from %s to %s)'), $offset, $limit);
            }
            if (Tools::getValue('truncate')) {
                $logMessage .= ' '.$this->l('with truncate');
            }
            Logger::addLog($logMessage, 1, null, $entityType, null, true, (int) $this->context->employee->id);
        } else {
            $this->errors[] = $this->l('To proceed, please upload a file first.');
        }
    }

    /**
     * @param string $entityType
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    protected function truncateTables($entityType)
    {
        // Delegate truncate functionality to entity type
        if (static::hasEntityType($entityType)) {
            $importEntityType = static::getEntityType($entityType);
            if ($importEntityType->supportTruncate()) {
                $result = $importEntityType->truncate();
                if ($result === true) {
                    return true;
                }
                foreach ($result as $error) {
                    $this->errors[] = $error;
                }
            }
            return false;
        }

        // fallback to core types / old implementation
        $conn = Db::getInstance();
        $mainImageExtensions = ImageManager::getAllowedImageExtensions(true, true);

        switch ($entityType) {
            case static::ENTITY_TYPE_CATEGORIES:
                try {
                    $conn->delete(
                        'category',
                        '`id_category` NOT IN ('.(int) Configuration::get('PS_HOME_CATEGORY').', '.(int) Configuration::get('PS_ROOT_CATEGORY').')'
                    );
                } catch (PrestaShopDatabaseException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to delete category from table `%s`'), 'category');
                }
                try {
                    $conn->delete(
                        'category_lang',
                        '`id_category` NOT IN ('.(int) Configuration::get('PS_HOME_CATEGORY').', '.(int) Configuration::get('PS_ROOT_CATEGORY').')'
                    );
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to delete category from table `%s`'), 'category_lang');
                }
                try {
                    $conn->delete(
                        'category_shop',
                        '`id_category` NOT IN ('.(int) Configuration::get('PS_HOME_CATEGORY').', '.(int) Configuration::get('PS_ROOT_CATEGORY').')'
                    );
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to delete category from table `%s`'), 'category_shop');
                }
                try {
                    $conn->execute('ALTER TABLE `'._DB_PREFIX_.'category` AUTO_INCREMENT = 3');
                } catch (PrestaShopException $e) {
                    $this->errors[] = $this->l('Failed to reset auto increment');

                    return false;
                }
                foreach (scandir(_PS_CAT_IMG_DIR_) as $d) {
                    if (preg_match('/^[0-9]+(-(.*))?\.('.implode('|', $mainImageExtensions).')$/', $d)) {
                        unlink(_PS_CAT_IMG_DIR_.$d);
                    }
                }
                break;
            case static::ENTITY_TYPE_PRODUCTS:
                foreach ([
                    'product',
                    'product_shop',
                    'feature_product',
                    'product_lang',
                    'category_product',
                    'product_tag',
                    'image',
                    'image_lang',
                    'image_shop',
                    'specific_price',
                    'specific_price_priority',
                    'product_carrier',
                    'cart_product',
                ] as $table) {
                    try {
                        $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.$table.'`');
                    } catch (PrestaShopException $e) {
                        $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), $table, $e->getMessage());
                    }
                }
                foreach ([
                    'product_attachment',
                    'product_country_tax',
                    'product_download',
                    'product_group_reduction_cache',
                    'product_sale',
                    'product_supplier',
                    'warehouse_product_location',
                    'stock',
                    'stock_available',
                    'stock_mvt',
                    'customization',
                    'customization_field',
                    'supply_order_detail',
                    'attribute_impact',
                    'product_attribute',
                    'product_attribute_shop',
                    'product_attribute_combination',
                    'product_attribute_image',
                    'pack',
                ] as $table) {
                    try {
                        $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.$table.'`');
                    } catch (PrestaShopException $e) {
                        $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), $table, $e->getMessage());
                    }
                }
                Image::deleteAllImages();
                if (!file_exists(_PS_PROD_IMG_DIR_)) {
                    mkdir(_PS_PROD_IMG_DIR_);
                }
                break;
            case static::ENTITY_TYPE_COMBINATIONS:
                foreach ([
                    'attribute',
                    'attribute_impact',
                    'attribute_lang',
                    'attribute_group',
                    'attribute_group_lang',
                    'attribute_group_shop',
                    'attribute_shop',
                    'product_attribute',
                    'product_attribute_shop',
                    'product_attribute_combination',
                    'product_attribute_image',
                ] as $table) {
                    try {
                        $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.$table.'`');
                    } catch (PrestaShopException $e) {
                        $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), $table, $e->getMessage());
                    }
                }

                try {
                    $conn->execute('DELETE FROM `'._DB_PREFIX_.'stock_available` WHERE id_product_attribute != 0');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to delete from table `%s`'), 'stock_available');
                }
                break;
            case static::ENTITY_TYPE_CUSTOMERS:
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'customer`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'customer', $e->getMessage());
                }
                break;
            case static::ENTITY_TYPE_ADDRESSES:
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'address`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'address', $e->getMessage());
                }
                break;
            case static::ENTITY_TYPE_MANUFACTURERS:
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'manufacturer', $e->getMessage());
                }
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer_lang`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'manufacturer_lang', $e->getMessage());
                }
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer_shop`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'manufacturer_shop', $e->getMessage());
                }
                foreach (scandir(_PS_MANU_IMG_DIR_) as $d) {
                    if (preg_match('/^[0-9]+(-(.*))?\.('.implode('|', $mainImageExtensions).')$/', $d)) {
                        unlink(_PS_MANU_IMG_DIR_.$d);
                    }
                }
                break;
            case static::ENTITY_TYPE_SUPPLIERS:
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'supplier`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'supplier', $e->getMessage());
                }
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'supplier_lang`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'supplier_lang', $e->getMessage());
                }
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'supplier_shop`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'supplier_shop', $e->getMessage());
                }
                foreach (scandir(_PS_SUPP_IMG_DIR_) as $d) {
                    if (preg_match('/^[0-9]+(-(.*))?\.('.implode('|', $mainImageExtensions).')$/', $d)) {
                        unlink(_PS_SUPP_IMG_DIR_.$d);
                    }
                }
                break;
            case static::ENTITY_TYPE_ALIAS:
                try {
                    $conn->execute('TRUNCATE TABLE `'._DB_PREFIX_.'alias`');
                } catch (PrestaShopException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to truncate table `%s`: %s'), 'alias', $e->getMessage());
                }
                break;
        }
        Image::clearTmpDir();

        return true;
    }

    /**
     * @param ImportEntityType $importEntityType
     *
     * @param int|bool $offset
     * @param int|bool $limit
     * @param array $crossStepsVariables
     * @param bool $validateOnly
     * @param int $moreStep
     *
     * @return int
     * @throws PrestaShopException
     */
    public function importGroup($importEntityType, $offset, $limit, $crossStepsVariables, $validateOnly, $moreStep)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $result = $importEntityType->import($info, $validateOnly);
                if (isset($result['errors']) && is_array($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $this->errors[] = $error;
                    }
                }
                if (isset($result['warnings']) && is_array($result['warnings'])) {
                    foreach ($result['warnings'] as $error) {
                        $this->warnings[] = $error;
                    }
                }
            } catch (Throwable $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param int|bool $offset
     * @param int|bool $limit
     * @param array|false $crossStepsVariables
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function categoryImport($offset = false, $limit = false, &$crossStepsVariables = false, $validateOnly = false)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        $idDefaultLanguage = (int) Configuration::get('PS_LANG_DEFAULT');
        $idLang = Language::getIdByIso(Tools::getValue('iso_lang'));
        if (!Validate::isUnsignedId($idLang)) {
            $idLang = $idDefaultLanguage;
        }
        static::setLocale();

        $forceIds = Tools::getValue('forceIDs');
        $regenerate = Tools::getValue('regenerate');
        $shopIsFeatureActive = Shop::isFeatureActive();

        $catMoved = [];
        if (is_array($crossStepsVariables) && array_key_exists('cat_moved', $crossStepsVariables)) {
            $catMoved = $crossStepsVariables['cat_moved'];
        }

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->categoryImportOne(
                    $info,
                    $idDefaultLanguage,
                    $idLang,
                    $forceIds,
                    $regenerate,
                    $shopIsFeatureActive,
                    $catMoved, // by ref
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        if (!$validateOnly) {
            /* Import has finished, we can regenerate the categories nested tree */
            try {
                Category::regenerateEntireNtree();
            } catch (PrestaShopException $e) {
                $this->warnings[] = sprintf($this->l('Unable to regenerate category tree: %s'), $e->getMessage());
            }
        }
        $datasource->close();

        if ($crossStepsVariables !== false) {
            $crossStepsVariables['cat_moved'] = $catMoved;
        }

        return $lineCount;
    }

    /**
     * @return void
     */
    protected function receiveTab()
    {
        $typeValue = Tools::getValue('type_value') ? Tools::getValue('type_value') : [];
        foreach ($typeValue as $nb => $type) {
            if ($type != 'no') {
                static::$columnMask[$type] = $nb;
            }
        }
    }

    /**
     * @param array $row
     *
     * @return array
     */
    public static function getMaskedRow($row)
    {
        $res = [];
        if (is_array(static::$columnMask)) {
            foreach (static::$columnMask as $type => $nb) {
                $res[$type] = isset($row[$nb]) ? trim($row[$nb]) : null;
            }
        }

        return $res;
    }

    /**
     * @param array $info
     * @param int $idDefaultLanguage
     * @param int $idLang
     * @param bool $forceIds
     * @param bool $regenerate
     * @param bool $shopIsFeatureActive
     * @param array $catMoved
     * @param bool $validateOnly
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function categoryImportOne($info, $idDefaultLanguage, $idLang, $forceIds, $regenerate, $shopIsFeatureActive, &$catMoved, $validateOnly = false)
    {
        $tabCateg = [Configuration::get('PS_HOME_CATEGORY'), Configuration::get('PS_ROOT_CATEGORY')];
        if (isset($info['id']) && in_array((int) $info['id'], $tabCateg)) {
            $this->errors[] = $this->l('The category ID must be unique. It can\'t be the same as the one for Root or Home category.');

            return;
        }
        static::setDefaultValues($info);

        if ($forceIds && isset($info['id']) && (int) $info['id']) {
            $category = new Category((int) $info['id']);
        } else {
            if (isset($info['id']) && (int) $info['id'] && Category::existsInDatabase((int) $info['id'], 'category')) {
                $category = new Category((int) $info['id']);
            } else {
                $category = new Category();
            }
        }

        array_walk($info, [static::class, 'fillInfo'], $category);

        // Parent category
        if (isset($category->parent) && is_numeric($category->parent)) {
            // Validation for parenting itself
            if ($validateOnly && ($category->parent == $category->id) || (isset($info['id']) && $category->parent == (int) $info['id'])) {
                $this->errors[] = sprintf(
                    $this->l(
                        'The category ID must be unique. It can\'t be the same as the one for the parent category (ID: %1$s).'
                    ),
                    [(!empty($info['id'])) ? $info['id'] : 'null']
                );

                return;
            }
            if (isset($catMoved[$category->parent])) {
                $category->parent = $catMoved[$category->parent];
            }
            $category->id_parent = $category->parent;
        } elseif (isset($category->parent) && is_string($category->parent)) {
            // Validation for parenting itself
            if ($validateOnly && isset($category->name) && ($category->parent == $category->name)) {
                $this->errors[] = sprintf($this->l('A category can\'t be its own parent. You should rename it (current name: %1$s).'), $category->parent);

                return;
            }
            $categoryParent = Category::searchByName($idLang, $category->parent, true);
            if ($categoryParent['id_category']) {
                $category->id_parent = (int) $categoryParent['id_category'];
                $category->level_depth = (int) $categoryParent['level_depth'] + 1;
            } else {
                $categoryToCreate = new Category();
                $categoryToCreate->name = static::createMultiLangField($category->parent);
                $categoryToCreate->active = 1;
                $categoryLinkRewrite = Tools::link_rewrite($categoryToCreate->name[$idLang]);
                $categoryToCreate->link_rewrite = static::createMultiLangField($categoryLinkRewrite);
                $categoryToCreate->id_parent = Configuration::get('PS_HOME_CATEGORY'); // Default parent is home for unknown category to create

                if (($fieldError = $categoryToCreate->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $categoryToCreate->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move the position of this test. Only ->add() should not be triggered is !validateOnly. Previous tests should be always run.
                    $categoryToCreate->add()
                ) {
                    $category->id_parent = $categoryToCreate->id;
                } else {
                    if (!$validateOnly) {
                        $this->errors[] = sprintf(
                            $this->l('%1$s (ID: %2$s) cannot be saved'),
                            $categoryToCreate->name[$idLang],
                            (!empty($categoryToCreate->id)) ? $categoryToCreate->id : 'null'
                        );
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
                    }
                }
            }
        }
        if (isset($category->link_rewrite) && !empty($category->link_rewrite[$idDefaultLanguage])) {
            $validLink = Validate::isLinkRewrite($category->link_rewrite[$idDefaultLanguage]);
        } else {
            $validLink = false;
        }

        if (!$shopIsFeatureActive) {
            $category->id_shop_default = 1;
        } else {
            $category->id_shop_default = (int) $this->context->shop->id;
        }

        $bak = $category->link_rewrite[$idDefaultLanguage];
        if ((isset($category->link_rewrite) && empty($category->link_rewrite[$idDefaultLanguage])) || !$validLink) {
            $category->link_rewrite = Tools::link_rewrite($category->name[$idDefaultLanguage]);
            if ($category->link_rewrite == '') {
                $category->link_rewrite = 'friendly-url-autogeneration-failed';
                $this->warnings[] = sprintf($this->l('URL rewriting failed to auto-generate a friendly URL for: %s'), $category->name[$idDefaultLanguage]);
            }
            $category->link_rewrite = static::createMultiLangField($category->link_rewrite);
        }

        if (!$validLink) {
            $this->informations[] = sprintf(
                $this->l('Rewrite link for %1$s (ID %2$s): re-written as %3$s.'),
                $bak,
                (!empty($info['id'])) ? $info['id'] : 'null',
                $category->link_rewrite[$idDefaultLanguage]
            );
        }
        $res = false;
        if (($fieldError = $category->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
            ($langFieldError = $category->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true && empty($this->errors)
        ) {
            $categoryAlreadyCreated = Category::searchByNameAndParentCategoryId(
                $idLang,
                $category->name[$idLang],
                $category->id_parent
            );

            // If category already in base, get id category back
            if ($categoryAlreadyCreated['id_category']) {
                $catMoved[$category->id] = (int) $categoryAlreadyCreated['id_category'];
                $category->id = (int) $categoryAlreadyCreated['id_category'];
                if (Validate::isDate($categoryAlreadyCreated['date_add'])) {
                    $category->date_add = $categoryAlreadyCreated['date_add'];
                }
            }

            if ($category->id && $category->id == $category->id_parent) {
                $this->errors[] = sprintf(
                    $this->l('A category cannot be its own parent. The parent category ID is either missing or unknown (ID: %1$s).'),
                    (!empty($info['id'])) ? $info['id'] : 'null'
                );

                return;
            }

            /* No automatic nTree regeneration for import */
            $category->doNotRegenerateNTree = true;

            // If id category AND id category already in base, trying to update
            $categoriesHomeRoot = [Configuration::get('PS_ROOT_CATEGORY'), Configuration::get('PS_HOME_CATEGORY')];
            if ($category->id &&
                $category->categoryExists($category->id) &&
                !in_array($category->id, $categoriesHomeRoot) &&
                !$validateOnly
            ) {
                $res = $category->update();
            }
            if ($category->id == Configuration::get('PS_ROOT_CATEGORY')) {
                $this->errors[] = $this->l('The root category cannot be modified.');
            }
            // If no id_category or update failed
            $category->force_id = (bool) $forceIds;
            if (!$res && !$validateOnly) {
                $res = $category->add();
            }
        }

        // ValidateOnly mode : stops here
        if ($validateOnly) {
            return;
        }

        //copying images of categories
        if (!empty($category->image)) {
            if (!(static::copyImg($category->id, null, $category->image, static::ENTITY_TYPE_CATEGORIES, !$regenerate, $this->warnings))) {
                $this->warnings[] = $category->image.' '.$this->l('cannot be copied.');
            }
        }
        // If both failed, mysql error
        if (!$res) {
            $this->errors[] = sprintf(
                $this->l('%1$s (ID: %2$s) cannot be saved'),
                (!empty($info['name'])) ? Tools::safeOutput($info['name']) : 'No Name',
                (!empty($info['id'])) ? Tools::safeOutput($info['id']) : 'No ID'
            );
            $errorTmp = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
            if ($errorTmp != '') {
                $this->errors[] = $errorTmp;
            }
        } else {
            // Associate category to shop
            if ($shopIsFeatureActive) {
                Db::getInstance()->delete(
                    'category_shop',
                    '`id_category` = '.(int) $category->id
                );

                if (empty($info['shop'])) {
                    $info['shop'] = implode($this->multiple_value_separator, Shop::getContextListShopID());
                }

                // Get shops for each attributes
                $info['shop'] = explode($this->multiple_value_separator, $info['shop']);

                foreach ($info['shop'] as $shop) {
                    if (!empty($shop) && !is_numeric($shop)) {
                        $category->addShop(Shop::getIdByName($shop));
                    } elseif (!empty($shop)) {
                        $category->addShop($shop);
                    }
                }
            }
        }
    }

    /**
     * @param array $info
     *
     * @return void
     */
    protected static function setDefaultValues(&$info)
    {
        foreach (static::$defaultValues as $k => $v) {
            if (!isset($info[$k]) || $info[$k] == '') {
                $info[$k] = $v;
            }
        }
    }

    /**
     * copyImg copy an image located in $url and save it in a path
     * according to $entity->$id_entity .
     * $id_image is used if we need to add a watermark
     *
     * @param int $idEntity id of product or category (set in entity)
     * @param int $idImage (default null) id of the image if watermark enabled.
     * @param string $url path or url to use
     * @param string $entityType entity type
     * @param bool $regenerate
     * @param string[] $errors collect errors
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function copyImg($idEntity, $idImage = null, $url = '', $entityType = 'products', $regenerate = true, &$errors = [])
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $url = urldecode(trim($url));
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['path'])) {
            $uri = ltrim($parsedUrl['path'], '/');
            $parts = explode('/', $uri);
            foreach ($parts as &$part) {
                $part = rawurlencode($part);
            }
            unset($part);
            $parsedUrl['path'] = '/'.implode('/', $parts);
        }

        if (isset($parsedUrl['query'])) {
            $queryParts = [];
            parse_str($parsedUrl['query'], $queryParts);
            $parsedUrl['query'] = http_build_query($queryParts);
        }

        $url = http_build_url('', $parsedUrl);

        $copyErr = null;
        if (! Tools::copy($url, $tmpfile, null, $copyErr)) {
            $msg = sprintf(Tools::displayError("Failed to download file '%s' to temporary file '%s'"), $url, $tmpfile);
            if ($copyErr) {
                $msg .= ': ' . $copyErr;
            }
            $errors[] = $msg;
            return false;
        }

        try {
            // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
            if (!ImageManager::checkImageMemoryLimit($tmpfile)) {
                $errors[] = sprintf(Tools::displayError("Failed to process image '%s': Not engough memory"), $url);
                return false;
            }

            // Find path depending on $entityType
            $imageEntity = ImageEntity::getImageEntityInfo($entityType);
            if (!$imageEntity) {
                throw new PrestaShopException("Image entity $entityType not found");
            }

            $path = $imageEntity['path'];
            $filename = $idEntity;

            if ($entityType === ImageEntity::ENTITY_TYPE_PRODUCTS) {
                $path .= Image::getImgFolderStatic($idImage);
                $filename = $idImage;
            }

            // Ensure the directory exists, or create it dynamically
            if (!is_dir($path)) {
                if (!mkdir($path, 0755, true) || !is_dir($path)) {
                    $errors[] = sprintf(Tools::displayError("Failed to create directory '%s'"), $path);
                    return false;
                }
            }

            // Create the new source image file
            $imageExtension = ImageManager::getDefaultImageExtension();

            $targetFile = $path . $filename . '.' . $imageExtension;
            if (! ImageManager::convertImageToExtension($tmpfile, $imageExtension, $targetFile)) {
                $error = sprintf(Tools::displayError("Failed to convert uploaded file to '%s' image format"), $imageExtension);
                $errors[] = $error;
                return false;
            }

            if ($regenerate) {
                // Generate all image types for source image
                ImageManager::generateImageTypesByEntity($entityType, $idEntity);
            }

            return true;
        } finally {
            if (file_exists($tmpfile)) {
                unlink($tmpfile);
            }
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    public function clearSmartyCache()
    {
        Tools::enableCache();
        Tools::clearCache($this->context->smarty);
        Tools::restoreCacheSettings();
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param array|false $crossStepsVariables
     * @param bool $validateOnly
     * @param int $moreStep
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public function productImport($offset = false, $limit = false, &$crossStepsVariables = false, $validateOnly = false, $moreStep = 0)
    {
        if ($moreStep == 1) {
            return $this->productImportAccessories($offset, $limit, $crossStepsVariables);
        }
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        $idDefaultLanguage = (int) Configuration::get('PS_LANG_DEFAULT');
        $idLang = Language::getIdByIso(Tools::getValue('iso_lang'));
        if (!Validate::isUnsignedId($idLang)) {
            $idLang = $idDefaultLanguage;
        }
        static::setLocale();
        $shopIds = Shop::getCompleteListOfShopsID();

        $forceCat = Tools::getValue('forceCat');
        $forceIds = Tools::getValue('forceIDs');
        $matchRef = Tools::getValue('match_ref');
        $regenerate = Tools::getValue('regenerate');
        $shopIsFeatureActive = Shop::isFeatureActive();
        if (!$validateOnly) {
            Module::setBatchMode(true);
        }

        $accessories = [];
        if (is_array($crossStepsVariables) && array_key_exists('accessories', $crossStepsVariables)) {
            $accessories = $crossStepsVariables['accessories'];
        }

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->productImportOne(
                    $info,
                    $idDefaultLanguage,
                    $idLang,
                    $forceIds,
                    $regenerate,
                    $shopIsFeatureActive,
                    $shopIds,
                    $matchRef,
                    $accessories, // by ref
                    $validateOnly,
                    $forceCat
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();
        if (!$validateOnly) {
            Module::processDeferedFuncCall();
            Module::processDeferedClearCache();
            Tag::updateTagCount();
        }

        if ($crossStepsVariables !== false) {
            $crossStepsVariables['accessories'] = $accessories;
        }

        return $lineCount;
    }

    /**
     * @param int $offset
     * @param int $limit
     * @param array|false $crossStepsVariables
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    protected function productImportAccessories($offset, $limit, &$crossStepsVariables)
    {
        if ($crossStepsVariables === false || !array_key_exists('accessories', $crossStepsVariables)) {
            return 0;
        }

        $accessories = $crossStepsVariables['accessories'];

        if ($offset == 0) {
            //             static::setLocale();
            Module::setBatchMode(true);
        }

        $lineCount = 0;
        $i = 0;
        foreach ($accessories as $productId => $links) {
            // skip elements until reaches offset
            if ($i < $offset) {
                $i++;
                continue;
            }

            if (count($links) > 0) { // We delete and relink only if there is accessories to link...
                // Bulk jobs: for performances, we need to do a minimum amount of SQL queries. No product inflation.
                $uniqueIds = static::getExistingIdsFromIdsOrRefs($links);
                try {
                    Db::getInstance()->delete('accessory', '`id_product_1` = '.(int) $productId);
                } catch (PrestaShopDatabaseException $e) {
                    $this->warnings[] = sprintf($this->l('Unable to delete from table `%s`: %s'), 'accessory', $e->getMessage());
                }
                static::changeAccessoriesForProduct($uniqueIds, $productId);
            }
            $lineCount++;

            // Empty value to reduce array weight (that goes through HTTP requests each time) but do not unset array entry!
            $accessories[$productId] = 0; // In JSON, 0 is lighter than null or false

            // stop when limit reached
            if ($lineCount >= $limit) {
                break;
            }
        }

        if ($lineCount < $limit) { // last pass only
            Module::processDeferedFuncCall();
            Module::processDeferedClearCache();
        }

        $crossStepsVariables['accessories'] = $accessories;

        return $lineCount;
    }

    /**
     * Gets a list of IDs from a list of IDs/Refs. The result will avoid duplicates, and checks if given IDs/Refs exists in DB.
     * Useful when a product list should be checked before a bulk operation on them (Only 1 query => performances).
     *
     * @param int|int[]|string|string[] $idsOrRefs
     *
     * @return array|false The IDs list, without duplicates and only existing ones.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getExistingIdsFromIdsOrRefs($idsOrRefs)
    {
        // separate IDs and Refs
        $ids = [];
        $refs = [];
        $whereStatements = [];
        foreach ((is_array($idsOrRefs) ? $idsOrRefs : [$idsOrRefs]) as $idOrRef) {
            if (is_numeric($idOrRef)) {
                $ids[] = (int) $idOrRef;
            } elseif (is_string($idOrRef)) {
                $refs[] = '\''.pSQL($idOrRef).'\'';
            }
        }
        // construct WHERE statement with OR combination
        if (count($ids) > 0) {
            $whereStatements[] = ' p.id_product IN ('.implode(',', $ids).') ';
        }
        if (count($refs) > 0) {
            $whereStatements[] = ' p.reference IN ('.implode(',', $refs).') ';
        }
        if (!count($whereStatements)) {
            return false;
        }
        try {
            $results = Db::readOnly()->getArray(
                (new DbQuery())
                    ->select('DISTINCT `id_product`')
                    ->from('product', 'p')
                    ->where(implode(' OR ', $whereStatements))
            );
        } catch (PrestaShopException $e) {
            return false;
        }
        // simplify array since there is 1 useless dimension.
        return array_column($results, 'id_product');
    }

    /**
     * Link accessories with product. No need to inflate a full Product (better performances).
     *
     * @param array $accessoriesId Accessories ids
     * @param int $productId The product ID to link accessories on.
     *
     * @return void
     */
    protected static function changeAccessoriesForProduct($accessoriesId, $productId)
    {
        foreach ($accessoriesId as $idProduct2) {
            try {
                Db::getInstance()->insert(
                    'accessory',
                    [
                        'id_product_1' => (int) $productId,
                        'id_product_2' => (int) $idProduct2,
                    ]
                );
            } catch (PrestaShopException $e) {
                Context::getContext()->controller->warnings[] = sprintf(('Unable to insert products into accessory table: %s'), $e->getMessage());
            }
        }
    }

    /**
     * @param array $info
     * @param int $idDefaultLanguage
     * @param int $idLang
     * @param bool $forceIds
     * @param bool $regenerate
     * @param bool $shopIsFeatureActive
     * @param int[] $shopIds
     * @param bool $matchRef
     * @param array $accessories
     * @param bool $validateOnly
     * @param bool $forceCat
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function productImportOne($info, $idDefaultLanguage, $idLang, $forceIds, $regenerate, $shopIsFeatureActive, $shopIds, $matchRef, &$accessories, $validateOnly = false, $forceCat = false)
    {
        $idProduct = null;
        $conn = Db::getInstance();
        // Use product reference as key.
        if ($matchRef && array_key_exists('reference', $info)) {
            $idReference = $conn->getValue(
                (new DbQuery())
                    ->select('p.`id_product`')
                    ->from('product', 'p')
                    ->join(Shop::addSqlAssociation('product', 'p'))
                    ->where('p.`reference` = \''.pSQL($info['reference']).'\'')
            );
            if ($idReference) {
                $idProduct = $idReference;
            }
        }

        // Force all ID numbers, overrides option Use product reference as key.
        if (array_key_exists('id', $info) && (int) $info['id'] && Product::existsInDatabase((int) $info['id'], 'product')) {
            if ($forceIds) {
                $idProduct = (int) $info['id'];
            } else {
                unset($info['id']);
            }
        }

        $product = new Product($idProduct);

        $updateAdvancedStockManagementValue = false;
        if (isset($product->id) && $product->id && Product::existsInDatabase((int) $product->id, 'product')) {
            $product->loadStockData();
            $updateAdvancedStockManagementValue = true;
            $product->category = $product->getCategories();
        }

        static::setEntityDefaultValues($product);
        array_walk($info, [static::class, 'fillInfo'], $product);

        if (!$shopIsFeatureActive) {
            $product->shop = (int) Configuration::get('PS_SHOP_DEFAULT');
        } elseif (empty($product->shop)) {
            $product->shop = implode($this->multiple_value_separator, Shop::getContextListShopID());
        }

        if (!$shopIsFeatureActive) {
            $product->id_shop_default = (int) Configuration::get('PS_SHOP_DEFAULT');
        } else {
            $product->id_shop_default = (int) $this->context->shop->id;
        }

        // link product to shops
        $product->id_shop_list = [];
        foreach (explode($this->multiple_value_separator, $product->shop) as $shop) {
            if (!empty($shop) && !is_numeric($shop)) {
                $product->id_shop_list[] = Shop::getIdByName($shop);
            } elseif (!empty($shop)) {
                $product->id_shop_list[] = $shop;
            }
        }

        if ((int) $product->id_tax_rules_group != 0) {
            if (Validate::isLoadedObject(new TaxRulesGroup($product->id_tax_rules_group))) {
                $address = $this->context->shop->getAddress();
                $taxManager = TaxManagerFactory::getManager($address, $product->id_tax_rules_group);
                $productTaxCalculator = $taxManager->getTaxCalculator();
                $product->tax_rate = $productTaxCalculator->getTotalRate();
            } else {
                $this->addProductWarning(
                    'id_tax_rules_group',
                    $product->id_tax_rules_group,
                    $this->l('Unknown tax rule group ID. You need to create a group with this ID first.')
                );
            }
        }
        if (isset($product->manufacturer) && is_numeric($product->manufacturer) && Manufacturer::manufacturerExists((int) $product->id_manufacturer)) {
            $product->id_manufacturer = (int) $product->manufacturer;
        } elseif (is_string($product->manufacturer) && !empty($product->manufacturer)) {
            if ($manufacturer = Manufacturer::getIdByName($product->manufacturer)) {
                $product->id_manufacturer = (int) $manufacturer;
            } else {
                $manufacturer = new Manufacturer();
                $manufacturer->name = $product->manufacturer;
                $manufacturer->active = true;
                if (($fieldError = $manufacturer->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $manufacturer->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $manufacturer->add()
                ) {
                    $product->id_manufacturer = (int) $manufacturer->id;
                    $manufacturer->associateTo($product->id_shop_list);
                } else {
                    if (!$validateOnly) {
                        $this->errors[] = sprintf(
                            $this->l('%1$s (ID: %2$s) cannot be saved'),
                            $manufacturer->name,
                            (!empty($manufacturer->id)) ? $manufacturer->id : 'null'
                        );
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').$conn->getMsgError();
                    }
                }
            }
        }

        if (isset($product->supplier) && is_numeric($product->supplier) && Supplier::supplierExists((int) $product->supplier)) {
            $product->id_supplier = (int) $product->supplier;
        } elseif (is_string($product->supplier) && !empty($product->supplier)) {
            if ($supplier = Supplier::getIdByName($product->supplier)) {
                $product->id_supplier = (int) $supplier;
            } else {
                $supplier = new Supplier();
                $supplier->name = $product->supplier;
                $supplier->active = true;

                if (($fieldError = $supplier->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $supplier->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly &&  // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $supplier->add()
                ) {
                    $product->id_supplier = (int) $supplier->id;
                    $supplier->associateTo($product->id_shop_list);
                } else {
                    if (!$validateOnly) {
                        $this->errors[] = sprintf(
                            $this->l('%1$s (ID: %2$s) cannot be saved'),
                            $supplier->name,
                            (!empty($supplier->id)) ? $supplier->id : 'null'
                        );
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').$conn->getMsgError();
                    }
                }
            }
        }

        if (isset($product->price_tex)) {
            $product->price = round(
                $product->price_tex,
                _TB_PRICE_DATABASE_PRECISION_
            );
        } elseif (isset($product->price_tin)) {
            $product->price = round(
                $product->price_tin,
                _TB_PRICE_DATABASE_PRECISION_
            );
            // If a tax is already included in price, withdraw it from price
            if ($product->tax_rate) {
                $product->price = round(
                    $product->price_tin / (1 + $product->tax_rate / 100),
                    _TB_PRICE_DATABASE_PRECISION_
                );
            }
        }

        if (!Configuration::get('PS_USE_ECOTAX')) {
            $product->ecotax = 0;
        }

        if (isset($product->category) && is_array($product->category) && count($product->category)) {
            $product->id_category = []; // Reset default values array
            foreach ($product->category as $value) {
                if (is_numeric($value)) {
                    if (Category::categoryExists((int) $value)) {
                        $product->id_category[] = (int) $value;
                    } else {
                        $categoryToCreate = new Category();
                        $categoryToCreate->id = (int) $value;
                        $categoryToCreate->name = static::createMultiLangField($value);
                        $categoryToCreate->active = 1;
                        $categoryToCreate->id_parent = Configuration::get('PS_HOME_CATEGORY'); // Default parent is home for unknown category to create
                        $categoryLinkRewrite = Tools::link_rewrite($categoryToCreate->name[$idDefaultLanguage]);
                        $categoryToCreate->link_rewrite = static::createMultiLangField($categoryLinkRewrite);
                        if (($fieldError = $categoryToCreate->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                            ($langFieldError = $categoryToCreate->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                            !$validateOnly &&  // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                            $categoryToCreate->add()
                        ) {
                            $product->id_category[] = (int) $categoryToCreate->id;
                        } else {
                            if (!$validateOnly) {
                                $this->errors[] = sprintf(
                                    $this->l('%1$s (ID: %2$s) cannot be saved'),
                                    $categoryToCreate->name[$idDefaultLanguage],
                                    (!empty($categoryToCreate->id)) ? $categoryToCreate->id : 'null'
                                );
                            }
                            if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                                $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').$conn->getMsgError();
                            }
                        }
                    }
                } elseif (!$validateOnly && is_string($value) && !empty($value)) {
                    $category = Category::searchByPath($idDefaultLanguage, trim($value), $this, 'productImportCreateCat');
                    if ($category['id_category']) {
                        $product->id_category[] = (int) $category['id_category'];
                    } else {
                        $this->errors[] = sprintf($this->l('%1$s cannot be saved'), trim($value));
                    }
                }
            }

            $product->id_category = array_values(array_unique($product->id_category));
        }

        // ensure that product will be associated with at least one category
        $productCategories = isset($product->id_category) && is_array($product->id_category)
            ? $product->id_category
            : $product->getCategories();
        if (! $productCategories) {
            $defaultProductShop = new Shop($product->id_shop_default);
            $rootCategory = Category::getRootCategory(null, Validate::isLoadedObject($defaultProductShop) ? $defaultProductShop : null);
            $rootCategoryId = (int)$rootCategory->id;
            $productCategories = [$rootCategoryId];
            $product->id_category = $productCategories;
        }

        // Will update default category if forced or if there is none set here
        $defaultCategoryId = (int)$product->id_category_default;
        if ($forceCat || !in_array($defaultCategoryId, $productCategories)) {
            $product->id_category_default = $productCategories[0];
        }

        $linkRewrite = (is_array($product->link_rewrite) && isset($product->link_rewrite[$idLang])) ? trim($product->link_rewrite[$idLang]) : '';
        $validLink = Validate::isLinkRewrite($linkRewrite);
        if (!$validLink) {
            if (isset($product->name[$idLang])) {
                $productName = $product->name[$idLang] ?? '';
                $linkRewrite = Tools::link_rewrite($product->name[$idLang] ?? '');
                if ($linkRewrite == '') {
                    $linkRewrite = 'friendly-url-autogeneration-failed';
                }

                $this->informations[] = sprintf(
                    $this->l('Rewrite link for %1$s (ID %2$s): re-written as %3$s.'),
                    $productName,
                    $idProduct ?? 'No ID',
                    $linkRewrite
                );
            } else {
                $linkRewrite = 'friendly-url-autogeneration-failed';
            }
        }

        if (!$validLink || !(is_array($product->link_rewrite) && count($product->link_rewrite))) {
            $product->link_rewrite = static::createMultiLangField($linkRewrite);
        } else {
            $product->link_rewrite[(int) $idLang] = $linkRewrite;
        }

        // replace the value of separator by coma
        if ($this->multiple_value_separator != ',') {
            if (is_array($product->meta_keywords)) {
                foreach ($product->meta_keywords as &$metaKeyword) {
                    if (!empty($metaKeyword)) {
                        $metaKeyword = str_replace($this->multiple_value_separator, ',', $metaKeyword);
                    }
                }
            }
        }

        // Convert comma into dot for all floating values
        foreach (Product::$definition['fields'] as $key => $array) {
            if ($array['type'] == Product::TYPE_FLOAT
                || $array['type'] == Product::TYPE_PRICE) {
                $product->{$key} = Tools::parseNumber($product->{$key});
            }
        }

        // Indexation is already 0 if it's a new product, but not if it's an update
        $product->indexed = 0;
        $productExistsInDatabase = false;

        if ($product->id && Product::existsInDatabase((int) $product->id, 'product')) {
            $productExistsInDatabase = true;
        }

        if (($matchRef && $product->reference && $product->existsRefInDatabase($product->reference)) || $productExistsInDatabase) {
            $product->date_upd = date('Y-m-d H:i:s');
        }

        $res = false;
        $fieldError = $product->validateFields(static::UNFRIENDLY_ERROR, true);
        $langFieldError = $product->validateFieldsLang(static::UNFRIENDLY_ERROR, true);
        if ($fieldError === true && $langFieldError === true) {
            // check quantity
            if ($product->quantity == null) {
                $product->quantity = 0;
            }

            // If match ref is specified && ref product && ref product already in base, trying to update
            if ($matchRef && $product->reference && $product->existsRefInDatabase($product->reference)) {
                $datas = $conn->getRow(
                    (new DbQuery())
                        ->select('`product_shop`.`date_add`, p.`id_product`')
                        ->from('product', 'p')
                        ->join(Shop::addSqlAssociation('product', 'p'))
                        ->where('p.`reference` = \''.pSQL($product->reference).'\'')
                );
                $product->id = (int) $datas['id_product'];
                $product->date_add = pSQL($datas['date_add']);
                $res = ($validateOnly || $product->update());
            } // Else If id product && id product already in base, trying to update
            elseif ($productExistsInDatabase) {
                $datas = $conn->getRow(
                    (new DbQuery())
                        ->select('`product_shop`.`date_add`')
                        ->from('product', 'p')
                        ->join(Shop::addSqlAssociation('product', 'p'))
                        ->where('p.`id_product` = '.(int) $product->id)
                );
                $product->date_add = pSQL($datas['date_add']);
                $res = ($validateOnly || $product->update());
            }
            // If no id_product or update failed
            $product->force_id = (bool) $forceIds;

            if (!$res) {
                if (isset($product->date_add) && $product->date_add != '') {
                    $res = ($validateOnly || $product->add(false));
                } else {
                    $res = ($validateOnly || $product->add());
                }
            }

            if (!$validateOnly) {

                if (isset($info['out_of_stock'])) {
                    StockAvailable::setProductOutOfStock((int) $product->id, (int)$info['out_of_stock']);
                }

                if ($productDownloadId = ProductDownload::getIdFromIdProduct((int) $product->id)) {
                    $productDownload = new ProductDownload($productDownloadId);

                    $productDownload->delete(true);
                }

                if ($product->getType() == Product::PTYPE_VIRTUAL) {
                    $productDownload = new ProductDownload();
                    $productDownload->filename = ProductDownload::getNewFilename();
                    Tools::copy($info['file_url'], _PS_DOWNLOAD_DIR_.$productDownload->filename);
                    $productDownload->id_product = (int) $product->id;
                    $productDownload->nb_downloadable = (int) $info['nb_downloadable'];
                    $productDownload->date_expiration = Tools::getDateFromDateFormat(Tools::getValue('date_format', 'Y-m-d'), $info['date_expiration']);
                    $productDownload->nb_days_accessible = (int) $info['nb_days_accessible'];
                    $productDownload->display_filename = basename($info['file_url']);
                    $productDownload->add();
                }
            }
        }

        $shops = [];
        $productShop = explode($this->multiple_value_separator, $product->shop);
        foreach ($productShop as $shop) {
            if (empty($shop)) {
                continue;
            }
            $shop = trim($shop);
            if (!empty($shop) && !is_numeric($shop)) {
                $shop = Shop::getIdByName($shop);
            }

            if (in_array($shop, $shopIds)) {
                $shops[] = $shop;
            } else {
                $this->addProductWarning(Tools::safeOutput($info['name']), $product->id, $this->l('Shop is not valid'));
            }
        }
        if (empty($shops)) {
            $shops = Shop::getContextListShopID();
        }
        // If both failed, mysql error
        if (!$res) {
            $this->errors[] = sprintf(
                $this->l('%1$s (ID: %2$s) cannot be saved'),
                (!empty($info['name'])) ? Tools::safeOutput($info['name']) : 'No Name',
                $idProduct ?? 'No ID'
            );
            $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
        } else {
            // Product supplier
            if (!$validateOnly && isset($product->id) && $product->id && isset($product->id_supplier) && property_exists($product, 'supplier_reference')) {
                $idProductSupplier = (int) ProductSupplier::getIdByProductAndSupplier((int) $product->id, 0, (int) $product->id_supplier);
                if ($idProductSupplier) {
                    $productSupplier = new ProductSupplier($idProductSupplier);
                } else {
                    $productSupplier = new ProductSupplier();
                }

                $productSupplier->id_product = (int) $product->id;
                $productSupplier->id_product_attribute = 0;
                $productSupplier->id_supplier = (int) $product->id_supplier;
                $productSupplier->product_supplier_price_te = round(
                    $product->wholesale_price,
                    _TB_PRICE_DATABASE_PRECISION_
                );
                $productSupplier->product_supplier_reference = $product->supplier_reference;
                $productSupplier->save();
            }

            // SpecificPrice (only the basic reduction feature is supported by the import)
            if (!$shopIsFeatureActive) {
                $info['shop'] = 1;
            } elseif (empty($info['shop'])) {
                $info['shop'] = implode($this->multiple_value_separator, Shop::getContextListShopID());
            }

            // Get shops for each attributes
            $info['shop'] = explode($this->multiple_value_separator, $info['shop']);

            $idShopList = [];
            foreach ($info['shop'] as $shop) {
                if (!empty($shop) && !is_numeric($shop)) {
                    $idShopList[] = (int) Shop::getIdByName($shop);
                } elseif (!empty($shop)) {
                    $idShopList[] = $shop;
                }
            }

            if ((isset($info['reduction_price']) && $info['reduction_price'] > 0) || (isset($info['reduction_percent']) && $info['reduction_percent'] > 0)) {
                foreach ($idShopList as $idShop) {
                    $specificPrice = SpecificPrice::getSpecificPrice($product->id, $idShop, 0, 0, 0, 1, 0, 0, 0, 0);

                    if (is_array($specificPrice) && isset($specificPrice['id_specific_price'])) {
                        $specificPrice = new SpecificPrice((int) $specificPrice['id_specific_price']);
                    } else {
                        $specificPrice = new SpecificPrice();
                    }
                    $specificPrice->id_product = (int) $product->id;
                    $specificPrice->id_specific_price_rule = 0;
                    $specificPrice->id_shop = $idShop;
                    $specificPrice->id_currency = 0;
                    $specificPrice->id_country = 0;
                    $specificPrice->id_group = 0;
                    $specificPrice->price = -1;
                    $specificPrice->id_customer = 0;
                    $specificPrice->from_quantity = 1;
                    $specificPrice->reduction = round(
                        (isset($info['reduction_price']) && $info['reduction_price']) ?
                        $info['reduction_price'] :
                        $info['reduction_percent'] / 100
                    );
                    $specificPrice->reduction_type = (isset($info['reduction_price']) && $info['reduction_price']) ? 'amount' : 'percentage';
                    $specificPrice->from = Tools::getDateFromDateFormat(Tools::getValue('date_format', 'Y-m-d'), $info['reduction_from']);
                    $specificPrice->to = Tools::getDateFromDateFormat(Tools::getValue('date_format', 'Y-m-d'), $info['reduction_to']);
                    if (!$validateOnly && !$specificPrice->save()) {
                        $this->addProductWarning(Tools::safeOutput($info['name']), $product->id, $this->l('Discount is invalid'));
                    }
                }
            }

            if (!$validateOnly && !empty($product->tags)) {
                if (isset($product->id) && $product->id) {
                    $tags = Tag::getProductTags($product->id);
                    if (is_array($tags) && count($tags)) {
                        $product->tags = $this->fieldExplode($product->tags);
                        if (is_array($product->tags) && count($product->tags)) {
                            foreach ($product->tags as $key => $tag) {
                                if (!empty($tag) && !empty(trim($tag))) {
                                    $product->tags[$key] = trim($tag);
                                } else {
                                    unset($product->tags[$key]);
                                }
                            }
                            $tags[$idLang] = $product->tags;
                            $product->tags = $tags;
                        }
                    }
                }
                // Delete tags for this id product, for no duplicating error
                Tag::deleteTagsForProduct($product->id);
                if (!is_array($product->tags)) {
                    $isTagAdded = Tag::addTags($idLang, $product->id, $this->fieldExplode($product->tags));
                    if (!$isTagAdded) {
                        $this->addProductWarning(Tools::safeOutput($info['name']), $product->id, $this->l('Tags list is invalid'));
                    }
                } else {
                    foreach ($product->tags as $key => $tags) {
                        $isTagAdded = Tag::addTags($key, $product->id, $tags);
                        if (!$isTagAdded) {
                            $this->addProductWarning(
                                Tools::safeOutput($info['name']),
                                (int) $product->id,
                                sprintf(
                                    $this->l('Invalid tag(s) (%s)'),
                                    implode(', ', $tags)
                                )
                            );
                        }
                    }
                }
            }

            //delete existing images if "delete_existing_images" is set to 1
            if (!$validateOnly && isset($product->delete_existing_images) && $product->delete_existing_images) {
                $product->deleteImages();
            }

            if (!$validateOnly && isset($product->image) && is_array($product->image) && count($product->image)) {
                $productHasImages = (bool) Image::getImages($this->context->language->id, (int) $product->id);
                foreach ($product->image as $key => $url) {
                    $url = trim($url);
                    $error = false;
                    if (!empty($url)) {
                        $url = str_replace(' ', '%20', $url);

                        $image = new Image();
                        $image->id_product = (int) $product->id;
                        $image->position = Image::getHighestPosition($product->id) + 1;
                        $image->cover = !$key && !$productHasImages;
                        if (isset($product->image_alt[$key])) {
                            $alt = substr($product->image_alt[$key], 0, 127); // Auto truncate
                            if (strlen($alt) > 0) {
                                $image->legend = static::createMultiLangField($alt);
                            }
                        }
                        // file_exists doesn't work with HTTP protocol
                        if ($image->validateFields(static::UNFRIENDLY_ERROR, true) === true &&
                            $image->validateFieldsLang(static::UNFRIENDLY_ERROR, true) === true &&
                            $image->add()
                        ) {
                            // associate image to selected shops
                            $image->associateTo($shops);
                            if (!static::copyImg($product->id, $image->id, $url, static::ENTITY_TYPE_PRODUCTS, !$regenerate, $this->warnings)) {
                                $image->delete();
                                $this->warnings[] = sprintf($this->l('Error copying image: %s'), $url);
                            }
                        } else {
                            $error = true;
                        }
                    } else {
                        $error = true;
                    }

                    if ($error) {
                        $this->warnings[] = sprintf($this->l('Product #%1$d: the picture (%2$s) cannot be saved.'), isset($image) ? $image->id_product : $product->id, $url);
                    }
                }
            }

            if (!$validateOnly && isset($product->id_category) && is_array($product->id_category)) {
                $product->updateCategories(array_map('intval', $product->id_category));
            }

            $product->checkDefaultAttributes();
            if (!$validateOnly && !$product->cache_default_attribute) {
                Product::updateDefaultAttribute($product->id);
            }

            // Features import
            $features = get_object_vars($product);

            if (!$validateOnly && !empty($features['features'])) {
                $featureValuesToImport = [];
                foreach (explode($this->multiple_value_separator, $features['features']) as $singleFeature) {
                    if (empty($singleFeature)) {
                        continue;
                    }
                    $tabFeature = explode(':', $singleFeature);
                    $featureName = isset($tabFeature[0]) ? trim($tabFeature[0]) : '';
                    $featureValue = isset($tabFeature[1]) ? trim($tabFeature[1]) : '';
                    $position = isset($tabFeature[2]) ? (int) $tabFeature[2] - 1 : false;
                    $featurePublicName = isset($tabFeature[3]) ? trim($tabFeature[0]) : null;
                    if (!empty($featureName) && !empty($featureValue)) {
                        $idFeature = (int) Feature::addFeatureImport($featureName, $position, $featurePublicName);
                        $idFeatureValue = (int) FeatureValue::addFeatureValueImport($idFeature, $featureValue, $idLang);
                        if (! isset($featureValuesToImport[$idFeature])) {
                            $featureValuesToImport[$idFeature] = [];
                        }
                        $featureValuesToImport[$idFeature][] = $idFeatureValue;
                    }
                }

                foreach ($featureValuesToImport as $idFeature => $featureValues) {
                    $product->deleteFeatureValues($idFeature);
                    foreach ($featureValues as $idFeatureValue) {
                        Product::addFeatureProductImport($product->id, $idFeature, $idFeatureValue);
                    }
                }
            }
            // clean feature positions to avoid conflict
            Feature::cleanPositions();

            // set advanced stock managament
            if (!$validateOnly && isset($product->advanced_stock_management)) {
                if ($product->advanced_stock_management != 1 && $product->advanced_stock_management != 0) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management has incorrect value. Not set for product %1$s '), $product->name[$idDefaultLanguage]);
                } elseif (!Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management == 1) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management is not enabled, cannot enable on product %1$s '), $product->name[$idDefaultLanguage]);
                } elseif ($updateAdvancedStockManagementValue) {
                    $product->setAdvancedStockManagement($product->advanced_stock_management);
                }
                // automatically disable depends on stock, if a_s_m set to disabled
                if (StockAvailable::dependsOnStock($product->id) && $product->advanced_stock_management == 0) {
                    StockAvailable::setProductDependsOnStock($product->id, 0);
                }
            }

            // Check if warehouse exists
            if (isset($product->warehouse) && $product->warehouse) {
                if (!Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management is not enabled, warehouse not set on product %1$s '), $product->name[$idDefaultLanguage]);
                } elseif (!$validateOnly) {
                    if (Warehouse::exists($product->warehouse)) {
                        // Get already associated warehouses
                        $associatedWarehousesCollection = WarehouseProductLocation::getCollection($product->id);

                        // Delete any entry in warehouse for this product
                        foreach ($associatedWarehousesCollection as $awc) {
                            $awc->delete();
                        }
                        $warehouseLocationEntity = new WarehouseProductLocation();
                        $warehouseLocationEntity->id_product = $product->id;
                        $warehouseLocationEntity->id_product_attribute = 0;
                        $warehouseLocationEntity->id_warehouse = $product->warehouse;
                        if (WarehouseProductLocation::getProductLocation($product->id, 0, $product->warehouse) !== false) {
                            $warehouseLocationEntity->update();
                        } else {
                            $warehouseLocationEntity->save();
                        }
                        StockAvailable::synchronize($product->id);
                    } else {
                        $this->warnings[] = sprintf($this->l('Warehouse did not exist, cannot set on product %1$s.'), $product->name[$idDefaultLanguage]);
                    }
                }
            }

            // stock available
            if (isset($product->depends_on_stock)) {
                if ($product->depends_on_stock != 0 && $product->depends_on_stock != 1) {
                    $this->warnings[] = sprintf($this->l('Incorrect value for "Depends on stock" for product %1$s '), $product->name[$idDefaultLanguage]);
                } elseif ((!$product->advanced_stock_management || $product->advanced_stock_management == 0) && $product->depends_on_stock == 1) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management is not enabled, cannot set "Depends on stock" for product %1$s '), $product->name[$idDefaultLanguage]);
                } elseif (!$validateOnly) {
                    StockAvailable::setProductDependsOnStock($product->id, $product->depends_on_stock);
                }

                // This code allows us to set qty and disable depends on stock
                if (!$validateOnly && isset($product->quantity)) {
                    // if depends on stock and quantity, add quantity to stock
                    if ($product->depends_on_stock == 1) {
                        $stockManager = StockManagerFactory::getManager();
                        $price = Tools::parseNumber($product->wholesale_price);
                        $warehouse = new Warehouse($product->warehouse);
                        if ($stockManager->addProduct((int) $product->id, 0, $warehouse, (int) $product->quantity, 1, $price, true)) {
                            StockAvailable::synchronize((int) $product->id);
                        }
                    } else {
                        if ($shopIsFeatureActive) {
                            foreach ($shops as $shop) {
                                StockAvailable::setQuantity((int) $product->id, 0, (int) $product->quantity, (int) $shop);
                            }
                        } else {
                            StockAvailable::setQuantity((int) $product->id, 0, (int) $product->quantity, (int) $this->context->shop->id);
                        }
                    }
                }
            } elseif (!$validateOnly) {
                // if not depends_on_stock set, use normal qty
                if ($shopIsFeatureActive) {
                    foreach ($shops as $shop) {
                        StockAvailable::setQuantity((int) $product->id, 0, (int) $product->quantity, (int) $shop);
                    }
                } else {
                    StockAvailable::setQuantity((int) $product->id, 0, (int) $product->quantity, (int) $this->context->shop->id);
                }
            }

            // Accessories linkage
            if (isset($product->accessories) && !$validateOnly && is_array($product->accessories) && count($product->accessories)) {
                $accessories[$product->id] = $product->accessories;
            }
        }
    }

    /**
     * @param ObjectModel $entity
     *
     * @return void
     */
    protected static function setEntityDefaultValues($entity)
    {
        $members = get_object_vars($entity);
        foreach (static::$defaultValues as $k => $v) {
            if ((array_key_exists($k, $members) && $entity->$k === null) || !array_key_exists($k, $members)) {
                $entity->$k = $v;
            }
        }
    }

    /**
     * @param string $productName
     * @param int|null $productId
     * @param string $message
     *
     * @return void
     */
    protected function addProductWarning($productName, $productId = null, $message = '')
    {
        $this->warnings[] = $productName.(isset($productId) ? ' (ID '.$productId.')' : '').' '.$message;
    }

    /**
     * Explode a field by multi-value separators. This is a bit more tricky
     * than a simple explode(), see https://www.ietf.org/rfc/rfc4180.txt and
     * https://en.wikipedia.org/wiki/Comma-separated_values.
     *
     * @param string $field Field to explode.
     *
     * @return array Array with single values as strings.
     */
    protected function fieldExplode($field)
    {
        if (!is_string($field)) {
            return [];
        }

        $field = trim($field, '"');
        $field = str_replace(
            [$this->separator, $this->multiple_value_separator],
            $this->separator,
            $field
        );

        return explode($this->separator, $field);
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function customerImport($offset = false, $limit = false, $validateOnly = false)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        $defaultLanguageId = (int) Configuration::get('PS_LANG_DEFAULT');
        $idLang = Language::getIdByIso(Tools::getValue('iso_lang'));
        if (!Validate::isUnsignedId($idLang)) {
            $idLang = $defaultLanguageId;
        }
        static::setLocale();

        $shopIsFeatureActive = Shop::isFeatureActive();
        $forceIds = Tools::getValue('forceIDs');

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->customerImportOne(
                    $info,
                    $defaultLanguageId,
                    $idLang,
                    $shopIsFeatureActive,
                    $forceIds,
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param array $info
     * @param int $defaultLanguageId
     * @param int $idLang
     * @param bool $shopIsFeatureActive
     * @param bool $forceIds
     * @param bool $validateOnly
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function customerImportOne($info, $defaultLanguageId, $idLang, $shopIsFeatureActive, $forceIds, $validateOnly = false)
    {
        static::setDefaultValues($info);

        if ($forceIds && isset($info['id']) && (int) $info['id']) {
            $customer = new Customer((int) $info['id']);
        } else {
            if (array_key_exists('id', $info) && (int) $info['id'] && Customer::customerIdExistsStatic((int) $info['id'])) {
                $customer = new Customer((int) $info['id']);
            } else {
                $customer = new Customer();
            }
        }

        $customerExist = false;
        $autodate = true;

        if (array_key_exists('id', $info) && (int) $info['id'] && Customer::customerIdExistsStatic((int) $info['id']) && Validate::isLoadedObject($customer)) {
            $currentIdCustomer = (int) $customer->id;
            $currentIdShop = (int) $customer->id_shop;
            $currentIdShopGroup = (int) $customer->id_shop_group;
            $customerExist = true;
            $customerGroups = $customer->getGroups();
            $addresses = $customer->getAddresses((int) Configuration::get('PS_LANG_DEFAULT'));
        }

        // Group Importation
        if (!empty($info['group'])) {
            foreach (explode($this->multiple_value_separator, $info['group']) as $group) {
                $group = trim($group);
                if (empty($group)) {
                    continue;
                }
                $idGroup = false;
                if (is_numeric($group)) {
                    $myGroup = new Group((int) $group);
                    if (Validate::isLoadedObject($myGroup)) {
                        $customerGroups[] = (int) $group;
                    }
                    continue;
                }
                $myGroup = Group::searchByName($group);
                if (isset($myGroup['id_group']) && $myGroup['id_group']) {
                    $idGroup = (int) $myGroup['id_group'];
                }
                if (!$idGroup) {
                    $myGroup = new Group();
                    $myGroup->name = [$idLang => $group];
                    if ($idLang != $defaultLanguageId) {
                        $myGroup->name = $myGroup->name + [$defaultLanguageId => $group];
                    }
                    $myGroup->price_display_method = 1;
                    if (!$validateOnly) {
                        $myGroup->add();
                        if (Validate::isLoadedObject($myGroup)) {
                            $idGroup = (int) $myGroup->id;
                        }
                    }
                }
                if ($idGroup) {
                    $customerGroups[] = (int) $idGroup;
                }
            }
        } elseif (isset($customer->id) && $customer->id) {
            $customerGroups = [0 => Configuration::get('PS_CUSTOMER_GROUP')];
        }

        if (!empty($info['date_add'])) {
            $autodate = false;
        }

        array_walk($info, [static::class, 'fillInfo'], $customer);

        if ($customer->passwd) {
            $customer->passwd = Tools::hash($customer->passwd);
        }

        $idShopList = explode($this->multiple_value_separator, $customer->id_shop);
        $customersShop = [];
        $customersShop['shared'] = [];
        $defaultShop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
        if ($shopIsFeatureActive && $idShopList) {
            foreach ($idShopList as $idShop) {
                if (empty($idShop)) {
                    continue;
                }
                $shop = new Shop((int) $idShop);
                $groupShop = $shop->getGroup();
                if ($groupShop->share_customer) {
                    if (!in_array($groupShop->id, $customersShop['shared'])) {
                        $customersShop['shared'][(int) $idShop] = $groupShop->id;
                    }
                } else {
                    $customersShop[(int) $idShop] = $groupShop->id;
                }
            }
        } else {
            $defaultShop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
            $defaultShop->getGroup();
            $customersShop[$defaultShop->id] = $defaultShop->getGroup()->id;
        }

        //set temporary for validate field
        $customer->id_shop = $defaultShop->id;
        $customer->id_shop_group = $defaultShop->getGroup()->id;
        if (!empty($info['id_default_group']) && !is_numeric($info['id_default_group'])) {
            $info['id_default_group'] = trim($info['id_default_group']);
            $myGroup = Group::searchByName($info['id_default_group']);
            if (isset($myGroup['id_group']) && $myGroup['id_group']) {
                $info['id_default_group'] = (int) $myGroup['id_group'];
            }
        }
        $myGroup = new Group($customer->id_default_group);
        if (!Validate::isLoadedObject($myGroup)) {
            $customer->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        }
        $customerGroups[] = (int) $customer->id_default_group;
        $customerGroups = array_flip(array_flip($customerGroups));

        // Bug when updating existing user that were csv-imported before...
        if (isset($customer->date_upd) && $customer->date_upd == '0000-00-00 00:00:00') {
            $customer->date_upd = date('Y-m-d H:i:s');
        }

        if($birthday = Tools::getDateFromDateFormat(Tools::getValue('date_format', 'Y-m-d'), $info['birthday'], 'Y-m-d')) {
            $customer->birthday = $birthday;
        }
        if($dateAdd = Tools::getDateFromDateFormat(Tools::getValue('date_format', 'Y-m-d'), $info['date_add'], 'Y-m-d')) {
            $customer->date_add = $dateAdd;
        }

        $res = false;
        if (($fieldError = $customer->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
            ($langFieldError = $customer->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
        ) {
            $res = true;
            $conn = Db::getInstance();
            foreach ($customersShop as $idShop => $idGroup) {
                $customer->force_id = (bool) $forceIds;
                if ($idShop == 'shared') {
                    foreach ($idGroup as $key => $id) {
                        $customer->id_shop = (int) $key;
                        $customer->id_shop_group = (int) $id;
                        if (isset($currentIdCustomer) && $customerExist && (isset($currentIdShopGroup) && (int) $currentIdShopGroup == (int) $id || isset($currentIdShop) && in_array($currentIdShop, ShopGroup::getShopsFromGroup($id)))) {
                            $customer->id = (int) $currentIdCustomer;
                            $res = ($validateOnly || $customer->update()) && $res;
                        } else {
                            $res = ($validateOnly || $customer->add($autodate)) && $res;
                            if (!$validateOnly && isset($addresses)) {
                                foreach ($addresses as $address) {
                                    $address['id_customer'] = $customer->id;
                                    unset($address['country'], $address['state'], $address['state_iso'], $address['id_address']);
                                    $conn->insert('address', $address, false, false);
                                }
                            }
                        }
                        if ($res && !$validateOnly && isset($customerGroups)) {
                            $customer->updateGroup($customerGroups);
                        }
                    }
                } else {
                    $customer->id_shop = $idShop;
                    $customer->id_shop_group = $idGroup;
                    if ($customerExist && isset($currentIdShop) && isset($currentIdCustomer) && (int) $idShop == (int) $currentIdShop) {
                        $customer->id = (int) $currentIdCustomer;
                        $res = ($validateOnly || $customer->update()) && $res;
                    } else {
                        $res = ($validateOnly || $customer->add($autodate)) && $res;
                        if (!$validateOnly && isset($addresses)) {
                            foreach ($addresses as $address) {
                                $address['id_customer'] = $customer->id;
                                unset($address['country'], $address['state'], $address['state_iso'], $address['id_address']);
                                $conn->insert('address', $address, false, false);
                            }
                        }
                    }
                    if ($res && !$validateOnly && isset($customerGroups)) {
                        $customer->updateGroup($customerGroups);
                    }
                }
            }
        }

        if (isset($customerGroups)) {
            unset($customerGroups);
        }
        if (isset($currentIdCustomer)) {
            unset($currentIdCustomer);
        }
        if (isset($currentIdShop)) {
            unset($currentIdShop);
        }
        if (isset($currentIdShopGroup)) {
            unset($currentIdShopGroup);
        }
        if (isset($addresses)) {
            unset($addresses);
        }

        if (!$res) {
            $this->errors[] = sprintf(
                $this->l('%1$s (ID: %2$s) cannot be %3$s'),
                $info['email'],
                (!empty($info['id'])) ? $info['id'] : 'null',
                ($validateOnly ? 'validated' : 'saved')
            );
            $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
        }
    }

    /**
     * @param int|bool $offset
     * @param int|bool $limit
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function addressImport($offset = false, $limit = false, $validateOnly = false)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $forceIds = Tools::getValue('forceIDs');

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->addressImportOne(
                    $info,
                    $forceIds,
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param array $info
     * @param bool $forceIds
     * @param bool $validateOnly
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function addressImportOne($info, $forceIds, $validateOnly = false)
    {
        static::setDefaultValues($info);

        if ($forceIds && isset($info['id']) && (int) $info['id']) {
            $address = new Address((int) $info['id']);
        } else {
            if (array_key_exists('id', $info) && (int) $info['id'] && Address::addressExists((int) $info['id'])) {
                $address = new Address((int) $info['id']);
            } else {
                $address = new Address();
            }
        }

        array_walk($info, [static::class, 'fillInfo'], $address);

        if (isset($address->country) && is_numeric($address->country)) {
            if (Country::getNameById(Configuration::get('PS_LANG_DEFAULT'), (int) $address->country)) {
                $address->id_country = (int) $address->country;
            }
        } elseif (is_string($address->country) && !empty($address->country)) {
            if ($idCountry = Country::getIdByName(null, $address->country)) {
                $address->id_country = (int) $idCountry;
            } else {
                $country = new Country();
                $country->active = 1;
                $country->name = static::createMultiLangField($address->country);
                $country->id_zone = 0; // Default zone for country to create
                $country->iso_code = mb_strtoupper(mb_substr($address->country, 0, 2)); // Default iso for country to create
                $country->contains_states = 0; // Default value for country to create
                $langFieldError = $country->validateFieldsLang(static::UNFRIENDLY_ERROR, true);
                if (($fieldError = $country->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $country->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $country->add()
                ) {
                    $address->id_country = (int) $country->id;
                } else {
                    if (!$validateOnly) {
                        $defaultLanguageId = (int) Configuration::get('PS_LANG_DEFAULT');
                        $this->errors[] = sprintf($this->l('%s cannot be saved'), $country->name[$defaultLanguageId]);
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
                    }
                }
            }
        }

        if (isset($address->state) && is_numeric($address->state)) {
            if (State::getNameById((int) $address->state)) {
                $address->id_state = (int) $address->state;
            }
        } elseif (is_string($address->state) && !empty($address->state)) {
            if ($idState = State::getIdByName($address->state)) {
                $address->id_state = (int) $idState;
            } else {
                $state = new State();
                $state->active = 1;
                $state->name = $address->state;
                $state->id_country = isset($country->id) ? (int) $country->id : 0;
                $state->id_zone = 0; // Default zone for state to create
                $state->iso_code = mb_strtoupper(mb_substr($address->state, 0, 2)); // Default iso for state to create
                $state->tax_behavior = 0;
                if (($fieldError = $state->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $state->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $state->add()
                ) {
                    $address->id_state = (int) $state->id;
                } else {
                    if (!$validateOnly) {
                        $this->errors[] = sprintf($this->l('%s cannot be saved'), $state->name);
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
                    }
                }
            }
        }

        if (!empty($address->customer_email)) {
            if (Validate::isEmail($address->customer_email)) {
                // a customer could exists in different shop
                $customerList = Customer::getCustomersByEmail($address->customer_email);

                if (count($customerList) == 0) {
                    $this->errors[] = sprintf(
                        $this->l('%1$s does not exist in database %2$s (ID: %3$s), and therefore cannot be %4$s'),
                        Db::getInstance()->getMsgError(),
                        $address->customer_email,
                        (!empty($info['id'])) ? $info['id'] : 'null',
                        ($validateOnly ? 'validated' : 'saved')
                    );
                }
            } else {
                $this->errors[] = sprintf($this->l('"%s" is not a valid email address.'), $address->customer_email);

                return;
            }
        } elseif (!empty($address->id_customer)) {
            if (Customer::customerIdExistsStatic((int) $address->id_customer)) {
                $customer = new Customer((int) $address->id_customer);

                // a customer could exists in different shop
                $customerList = Customer::getCustomersByEmail($customer->email);

                if (count($customerList) == 0) {
                    $this->errors[] = sprintf(
                        $this->l('%1$s does not exist in database %2$s (ID: %3$s), and therefore cannot be %4$s'),
                        Db::getInstance()->getMsgError(),
                        $customer->email,
                        (int) $address->id_customer,
                        ($validateOnly ? 'validated' : 'saved')
                    );
                }
            } else {
                $this->errors[] = sprintf(
                    $this->l('The customer ID #%d does not exist in the database, and therefore cannot be %2$s'),
                    $address->id_customer,
                    ($validateOnly ? 'validated' : 'saved')
                );
            }
        } else {
            $customerList = [];
            $address->id_customer = 0;
        }

        if (isset($address->manufacturer) && is_numeric($address->manufacturer) && Manufacturer::manufacturerExists((int) $address->id_manufacturer)) {
            $address->id_manufacturer = (int) $address->manufacturer;
        } elseif (is_string($address->manufacturer) && !empty($address->manufacturer)) {
            if ($manufacturerId = Manufacturer::getIdByName($address->manufacturer)) {
                $address->id_manufacturer = $manufacturerId;
            } else {
                $manufacturer = new Manufacturer();
                $manufacturer->name = $address->manufacturer;
                if (($fieldError = $manufacturer->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $manufacturer->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $manufacturer->add()
                ) {
                    $address->id_manufacturer = (int) $manufacturer->id;
                } else {
                    if (!$validateOnly) {
                        $this->errors[] = Db::getInstance()->getMsgError().' '.sprintf(
                            $this->l('%1$s (ID: %2$s) cannot be saved'),
                            $manufacturer->name,
                            (!empty($manufacturer->id)) ? $manufacturer->id : 'null'
                        );
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
                    }
                }
            }
        }

        if (isset($address->supplier) && is_numeric($address->supplier) && Supplier::supplierExists((int) $address->supplier)) {
            $address->id_supplier = (int) $address->supplier;
        } elseif (is_string($address->supplier) && !empty($address->supplier)) {
            if ($supplierId = Supplier::getIdByName($address->supplier)) {
                $address->id_supplier = $supplierId;
            } else {
                $supplier = new Supplier();
                $supplier->name = $address->supplier;
                if (($fieldError = $supplier->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $supplier->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $supplier->add()
                ) {
                    $address->id_supplier = (int) $supplier->id;
                } else {
                    if (!$validateOnly) {
                        $this->errors[] = Db::getInstance()->getMsgError().' '.sprintf(
                                $this->l('%1$s (ID: %2$s) cannot be saved'),
                                $supplier->name,
                                (!empty($supplier->id)) ? $supplier->id : 'null'
                            );
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
                    }
                }
            }
        }

        $res = false;
        if (($fieldError = $address->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
            ($langFieldError = $address->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
        ) {
            $address->force_id = (bool) $forceIds;

            if (isset($customerList) && count($customerList) > 0) {
                $filterList = [];
                foreach ($customerList as $customer) {
                    if (in_array($customer['id_customer'], $filterList)) {
                        continue;
                    }

                    $filterList[] = $customer['id_customer'];
                    $address->id_customer = $customer['id_customer'];
                }
            }

            if ($address->id && $address->addressExists($address->id)) {
                $res = ($validateOnly || $address->update());
            }
            if (!$res) {
                $res = ($validateOnly || $address->add());
            }
        }
        if (!$res) {
            if (!$validateOnly) {
                $this->errors[] = sprintf(
                    $this->l('%1$s (ID: %2$s) cannot be saved'),
                    $info['alias'],
                    (!empty($info['id'])) ? $info['id'] : 'null'
                );
            }
            if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
            }
        }
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param array|bool $crossStepsVariables
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function attributeImport($offset = false, $limit = false, &$crossStepsVariables = false, $validateOnly = false)
    {
        $defaultLanguage = Configuration::get('PS_LANG_DEFAULT');

        $groups = [];
        if (is_array($crossStepsVariables) && array_key_exists('groups', $crossStepsVariables)) {
            $groups = $crossStepsVariables['groups'];
        }
        foreach (AttributeGroup::getAttributesGroups($defaultLanguage) as $group) {
            $groups[$group['name']] = (int) $group['id_attribute_group'];
        }

        $attributes = [];
        if (is_array($crossStepsVariables) && array_key_exists('attributes', $crossStepsVariables)) {
            $attributes = $crossStepsVariables['attributes'];
        }
        foreach (ProductAttribute::getAttributes($defaultLanguage) as $attribute) {
            $attributes[$attribute['attribute_group'].'_'.$attribute['name']] = (int) $attribute['id_attribute'];
        }

        $deletedProducts = [];
        if (is_array($crossStepsVariables) && array_key_exists('deletedProducts', $crossStepsVariables) && is_array($crossStepsVariables['deletedProducts'])) {
            $deletedProducts = $crossStepsVariables['deletedProducts'];
        }

        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $regenerate = Tools::getValue('regenerate');
        $shopIsFeatureActive = Shop::isFeatureActive();

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;


            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);
            $info = array_map('trim', $info);

            try {
                $this->attributeImportOne(
                    $info,
                    $defaultLanguage,
                    $groups, // by ref
                    $attributes, // by ref
                    $regenerate,
                    $shopIsFeatureActive,
                    $validateOnly,
                    $deletedProducts // by ref
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        if ($crossStepsVariables !== false) {
            $crossStepsVariables['groups'] = $groups;
            $crossStepsVariables['attributes'] = $attributes;
            $crossStepsVariables['deletedProducts'] = $deletedProducts;
        }

        return $lineCount;
    }

    /**
     * @param array $info
     * @param int $defaultLanguage
     * @param array $groups
     * @param array $attributes
     * @param bool $regenerate
     * @param bool $shopIsFeatureActive
     * @param bool $validateOnly
     * @param int[] $deletedProducts
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function attributeImportOne($info, $defaultLanguage, &$groups, &$attributes, $regenerate, $shopIsFeatureActive, $validateOnly, &$deletedProducts)
    {
        static::setDefaultValues($info);

        if (!$shopIsFeatureActive) {
            $info['shop'] = 1;
        } elseif (empty($info['shop'])) {
            $info['shop'] = implode($this->multiple_value_separator, Shop::getContextListShopID());
        }

        // Get shops for each attributes
        $info['shop'] = explode($this->multiple_value_separator, $info['shop']);

        $idShopList = [];
        if (is_array($info['shop']) && count($info['shop'])) {
            foreach ($info['shop'] as $shop) {
                if (!empty($shop) && !is_numeric($shop)) {
                    $idShopList[] = Shop::getIdByName($shop);
                } elseif (!empty($shop)) {
                    $idShopList[] = $shop;
                }
            }
        }

        if (isset($info['id_product']) && $info['id_product']) {
            $product = new Product((int) $info['id_product'], false, $defaultLanguage);
        } elseif (Tools::getValue('match_ref') && isset($info['product_reference']) && $info['product_reference']) {
            $datas = Db::readOnly()->getRow(
                (new DbQuery())
                    ->select('p.`id_product`')
                    ->from('product', 'p')
                    ->join(Shop::addSqlAssociation('product', 'p'))
                    ->where('p.`reference` = \''.pSQL($info['product_reference']).'\'')
            );
            if (isset($datas['id_product']) && $datas['id_product']) {
                $product = new Product((int) $datas['id_product'], false, $defaultLanguage);
            } else {
                return;
            }
        } else {
            return;
        }

        // delete combinations for product
        $deleteCombinationsForProduct = Tools::getValue('only_file_product');
        if ($deleteCombinationsForProduct && !in_array((int)$product->id, $deletedProducts)) {
            $deletedProducts[] = (int)$product->id;
            $product->deleteProductAttributes();
        }

        $idImage = [];

        if (isset($info['image_url']) && $info['image_url']) {
            $info['image_url'] = explode($this->multiple_value_separator, $info['image_url']);

            if (is_array($info['image_url']) && count($info['image_url'])) {
                foreach ($info['image_url'] as $key => $url) {
                    $url = trim($url);
                    $productHasImages = (bool) Image::getImages($this->context->language->id, $product->id);

                    $image = new Image();
                    $image->id_product = (int) $product->id;
                    $image->position = Image::getHighestPosition($product->id) + 1;
                    $image->cover = !$productHasImages;

                    if (isset($info['image_alt'])) {
                        $alt = static::split($info['image_alt']);
                        if (isset($alt[$key]) && strlen($alt[$key]) > 0) {
                            $alt = static::createMultiLangField($alt[$key]);
                            $image->legend = $alt;
                        }
                    }

                    $fieldError = $image->validateFields(static::UNFRIENDLY_ERROR, true);
                    $langFieldError = $image->validateFieldsLang(static::UNFRIENDLY_ERROR, true);

                    if ($fieldError === true &&
                        $langFieldError === true &&
                        !$validateOnly &&
                        $image->add()
                    ) {
                        $image->associateTo($idShopList);
// FIXME: 2s/image !
                        if (!static::copyImg($product->id, $image->id, $url, static::ENTITY_TYPE_PRODUCTS, !$regenerate, $this->warnings)) {
                            $this->warnings[] = sprintf($this->l('Error copying image: %s'), $url);
                            $image->delete();
                        } else {
                            $idImage[] = (int) $image->id;
                        }
// until here
                    } else {
                        if (!$validateOnly) {
                            $this->warnings[] = sprintf(
                                $this->l('%s cannot be saved'),
                                (isset($image->id_product) ? ' ('.$image->id_product.')' : '')
                            );
                        }
                        if ($fieldError !== true || $langFieldError !== true) {
                            $errorMessage = Db::getInstance()->getMsgError();
                            $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').$errorMessage;
                        }
                    }
                }
            }
        } elseif (isset($info['image_position']) && $info['image_position']) {
            $info['image_position'] = explode($this->multiple_value_separator, $info['image_position']);

            if (is_array($info['image_position']) && count($info['image_position'])) {
                foreach ($info['image_position'] as $position) {
                    // choose images from product by position
                    $images = $product->getImages($defaultLanguage);

                    if ($images) {
                        foreach ($images as $row) {
                            if ($row['position'] == (int) $position) {
                                $idImage[] = (int) $row['id_image'];
                                break;
                            }
                        }
                    }
                    if (empty($idImage)) {
                        $this->warnings[] = sprintf(
                            $this->l('No image was found for combination with id_product = %s and image position = %s.'),
                            $product->id,
                            (int) $position
                        );
                    }
                }
            }
        }

        $idAttributeGroup = 0;
        // groups
        $groupsAttributes = [];
        if (isset($info['group'])) {
            foreach (explode($this->multiple_value_separator, $info['group']) as $key => $group) {
                if (empty($group)) {
                    continue;
                }
                $tabGroup = explode(':', $group);
                $group = trim($tabGroup[0]);
                if (!isset($tabGroup[1])) {
                    $type = 'select';
                } else {
                    $type = trim($tabGroup[1]);
                }

                // sets group
                $groupsAttributes[$key]['group'] = $group;

                // if position is filled
                if (isset($tabGroup[2])) {
                    $position = trim($tabGroup[2]);
                } else {
                    $position = false;
                }

                if (!isset($groups[$group])) {
                    $obj = new AttributeGroup();
                    $obj->is_color_group = false;
                    $obj->group_type = pSQL($type);
                    $obj->name[$defaultLanguage] = $group;
                    $obj->public_name[$defaultLanguage] = $group;
                    $obj->position = (!$position) ? AttributeGroup::getHigherPosition() + 1 : $position;

                    if (($fieldError = $obj->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                        ($langFieldError = $obj->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
                    ) {
                        // here, cannot avoid attributeGroup insertion to avoid an error during validation step.
                        //if (!$validateOnly) {
                        $obj->add();
                        $obj->associateTo($idShopList);
                        $groups[$group] = $obj->id;
                        //}
                    } else {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '');
                    }

                    // fills groups attributes
                    $idAttributeGroup = $obj->id;
                    $groupsAttributes[$key]['id'] = $idAttributeGroup;
                } else {
                    // already exists

                    $idAttributeGroup = $groups[$group];
                    $groupsAttributes[$key]['id'] = $idAttributeGroup;
                }
            }
        }

        // inits attribute
        $idProductAttribute = 0;
        $idProductAttributeUpdate = false;
        $attributesToAdd = [];

        // for each attribute
        if (isset($info['attribute'])) {
            foreach (explode($this->multiple_value_separator, $info['attribute']) as $key => $attribute) {
                if (empty($attribute)) {
                    continue;
                }
                $tabAttribute = explode(':', $attribute);
                $attribute = trim($tabAttribute[0]);
                // if position is filled
                if (isset($tabAttribute[1])) {
                    $position = trim($tabAttribute[1]);
                } else {
                    $position = false;
                }

                if (isset($groupsAttributes[$key])) {
                    $group = $groupsAttributes[$key]['group'];
                    if (!isset($attributes[$group.'_'.$attribute]) && count($groupsAttributes[$key]) == 2) {
                        $idAttributeGroup = $groupsAttributes[$key]['id'];
                        $obj = new ProductAttribute();
                        // sets the proper id (corresponding to the right key)
                        $obj->id_attribute_group = $groupsAttributes[$key]['id'];
                        $obj->name[$defaultLanguage] = str_replace('\n', '', str_replace('\r', '', $attribute));
                        $obj->position = (!$position && isset($groups[$group])) ? ProductAttribute::getHigherPosition($groups[$group]) + 1 : $position;

                        if (($fieldError = $obj->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                            ($langFieldError = $obj->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
                        ) {
                            if (!$validateOnly) {
                                $obj->add();
                                $obj->associateTo($idShopList);
                                $attributes[$group.'_'.$attribute] = $obj->id;
                            }
                        } else {
                            $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '');
                        }
                    }

                    $info['minimal_quantity'] = isset($info['minimal_quantity']) && $info['minimal_quantity'] ? (int) $info['minimal_quantity'] : 1;

                    $info['wholesale_price'] = Tools::parseNumber($info['wholesale_price']);
                    $info['price'] = Tools::parseNumber($info['price']);
                    $info['ecotax'] = Tools::parseNumber($info['ecotax']);
                    $info['weight'] = Tools::parseNumber($info['weight']);
                    $info['available_date'] = Tools::getDateFromDateFormat(Tools::getValue('date_format', 'Y-m-d'), $info['available_date']);

                    if (!Validate::isEan13($info['ean13'])) {
                        $this->warnings[] = sprintf($this->l('EAN13 "%1s" has incorrect value for product with id %2d.'), $info['ean13'], $product->id);
                        $info['ean13'] = '';
                    }

                    if ($info['default_on'] && !$validateOnly) {
                        $product->deleteDefaultAttributes();
                    }

                    // if a reference is specified for this product, get the associate id_product_attribute to UPDATE
                    if (!empty($info['reference'])) {
                        $idProductAttribute = Combination::getIdByReference($product->id, strval($info['reference']));

                        // updates the attribute
                        if ($idProductAttribute && !$validateOnly) {
                            // gets all the combinations of this product
                            $attributeCombinations = $product->getAttributeCombinations($defaultLanguage);
                            foreach ($attributeCombinations as $attributeCombination) {
                                if ($idProductAttribute && in_array($idProductAttribute, $attributeCombination)) {
                                    $product->updateAttribute(
                                        $idProductAttribute,
                                        (float) $info['wholesale_price'],
                                        (float) $info['price'],
                                        (float) $info['weight'],
                                        0,
                                        (Configuration::get('PS_USE_ECOTAX') ? (float) $info['ecotax'] : 0),
                                        $idImage,
                                        (string) $info['reference'],
                                        (string) $info['ean13'],
                                        (int) $info['default_on'],
                                        0,
                                        (string) $info['upc'],
                                        (int) $info['minimal_quantity'],
                                        $info['available_date'],
                                        false,
                                        $idShopList,
                                        $info['width'],
                                        $info['height'],
                                        $info['depth']
                                    );
                                    $idProductAttributeUpdate = true;
                                    if (!empty($info['supplier_reference'])) {
                                        $product->addSupplierReference($product->id_supplier, $idProductAttribute, $info['supplier_reference']);
                                    }
// until here
                                }
                            }
                        }
                    }

                    // if no attribute reference is specified, creates a new one
                    if (!$idProductAttribute && !$validateOnly) {
                        $idProductAttribute = $product->addCombinationEntity(
                            (float) $info['wholesale_price'],
                            (float) $info['price'],
                            (float) $info['weight'],
                            0,
                            (Configuration::get('PS_USE_ECOTAX') ? (float) $info['ecotax'] : 0),
                            (int) $info['quantity'],
                            $idImage,
                            (string) $info['reference'],
                            0,
                            (string) $info['ean13'],
                            (int) $info['default_on'],
                            0,
                            (string) $info['upc'],
                            (int) $info['minimal_quantity'],
                            $idShopList,
                            $info['available_date']
                        );

                        if (!empty($info['supplier_reference'])) {
                            $product->addSupplierReference($product->id_supplier, $idProductAttribute, $info['supplier_reference']);
                        }
                    }

                    // fills our attributes array, in order to add the attributes to the product_attribute afterwards
                    if (isset($attributes[$group.'_'.$attribute])) {
                        $attributesToAdd[] = (int) $attributes[$group.'_'.$attribute];
                    }

                    // after insertion, we clean attribute position and group attribute position
                    if (!$validateOnly) {
                        $obj = new ProductAttribute();
                        $obj->cleanPositions((int) $idAttributeGroup, false);
                        AttributeGroup::cleanPositions();
                    }
                }
            }
        }

        $product->checkDefaultAttributes();
        if (!$product->cache_default_attribute && !$validateOnly) {
            Product::updateDefaultAttribute($product->id);
        }
        if ($idProductAttribute) {
            if (!$validateOnly) {
                $conn = Db::getInstance();
                // now adds the attributes in the attribute_combination table
                if ($idProductAttributeUpdate) {
                    $conn->delete(
                        'product_attribute_combination',
                        '`id_product_attribute` = '.(int) $idProductAttribute
                    );
                }

                foreach ($attributesToAdd as $attributeToAdd) {
                    $conn->insert(
                        'product_attribute_combination',
                        [
                            'id_attribute' => (int) $attributeToAdd,
                            'id_product_attribute' => (int) $idProductAttribute,
                        ],
                        false,
                        false,
                        Db::INSERT_IGNORE
                    );
                }
            }

            // set advanced stock managment
            if (isset($info['advanced_stock_management'])) {
                if ($info['advanced_stock_management'] != 1 && $info['advanced_stock_management'] != 0) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management has incorrect value. Not set for product with id %d.'), $product->id);
                } elseif (!Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $info['advanced_stock_management'] == 1) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management is not enabled, cannot enable on product with id %d.'), $product->id);
                } elseif (!$validateOnly) {
                    $product->setAdvancedStockManagement($info['advanced_stock_management']);
                }
                // automatically disable depends on stock, if a_s_m set to disabled
                if (!$validateOnly && StockAvailable::dependsOnStock($product->id) && $info['advanced_stock_management'] == 0) {
                    StockAvailable::setProductDependsOnStock($product->id, 0, null, $idProductAttribute);
                }
            }

            // Check if warehouse exists
            if (isset($info['warehouse']) && $info['warehouse']) {
                if (!Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management is not enabled, warehouse is not set on product with id %d.'), $product->id);
                } else {
                    if (Warehouse::exists($info['warehouse'])) {
                        $warehouseLocationEntity = new WarehouseProductLocation();
                        $warehouseLocationEntity->id_product = $product->id;
                        $warehouseLocationEntity->id_product_attribute = $idProductAttribute;
                        $warehouseLocationEntity->id_warehouse = $info['warehouse'];
                        if (!$validateOnly) {
                            if (WarehouseProductLocation::getProductLocation($product->id, $idProductAttribute, $info['warehouse']) !== false) {
                                $warehouseLocationEntity->update();
                            } else {
                                $warehouseLocationEntity->save();
                            }
                            StockAvailable::synchronize($product->id);
                        }
                    } else {
                        $this->warnings[] = sprintf($this->l('Warehouse did not exist, cannot set on product %1$s.'), $product->name[$defaultLanguage]);
                    }
                }
            }

            // stock available
            if (isset($info['depends_on_stock'])) {
                if ($info['depends_on_stock'] != 0 && $info['depends_on_stock'] != 1) {
                    $this->warnings[] = sprintf($this->l('Incorrect value for "Depends on stock" for product %1$s '), $product->name[$defaultLanguage]);
                } elseif ((!$info['advanced_stock_management'] || $info['advanced_stock_management'] == 0) && $info['depends_on_stock'] == 1) {
                    $this->warnings[] = sprintf($this->l('Advanced stock management is not enabled, cannot set "Depends on stock" for product %1$s '), $product->name[$defaultLanguage]);
                } elseif (!$validateOnly) {
                    StockAvailable::setProductDependsOnStock($product->id, $info['depends_on_stock'], null, $idProductAttribute);
                }

                // This code allows us to set qty and disable depends on stock
                if (isset($info['quantity'])) {
                    // if depends on stock and quantity, add quantity to stock
                    if ($info['depends_on_stock'] == 1) {
                        $stockManager = StockManagerFactory::getManager();
                        $price = Tools::parseNumber($info['wholesale_price']);
                        $warehouse = new Warehouse($info['warehouse']);
                        if (!$validateOnly && $stockManager->addProduct((int) $product->id, $idProductAttribute, $warehouse, (int) $info['quantity'], 1, $price, true)) {
                            StockAvailable::synchronize((int) $product->id);
                        }
                    } elseif (!$validateOnly) {
                        if ($shopIsFeatureActive) {
                            foreach ($idShopList as $shop) {
                                StockAvailable::setQuantity((int) $product->id, $idProductAttribute, (int) $info['quantity'], (int) $shop);
                            }
                        } else {
                            StockAvailable::setQuantity((int) $product->id, $idProductAttribute, (int) $info['quantity'], $this->context->shop->id);
                        }
                    }
                }
            } elseif (!$validateOnly) { // if not depends_on_stock set, use normal qty
                if ($shopIsFeatureActive) {
                    foreach ($idShopList as $shop) {
                        StockAvailable::setQuantity((int) $product->id, $idProductAttribute, (int) $info['quantity'], (int) $shop);
                    }
                } else {
                    StockAvailable::setQuantity((int) $product->id, $idProductAttribute, (int) $info['quantity'], $this->context->shop->id);
                }
            }
        }
    }

    /**
     * @param string $field
     *
     * @return array
     */
    protected static function split($field)
    {
        if (empty($field)) {
            return [];
        }

        $separator = Tools::getValue('multiple_value_separator');
        if (is_null($separator) || trim($separator) == '') {
            $separator = ',';
        }

        $uniqidPath = false;

        // try data:// protocole. If failed, old school file on filesystem.
        if (($fd = @fopen('data://text/plain;base64,'.base64_encode($field), 'rb')) === false) {
            do {
                $uniqidPath = _PS_UPLOAD_DIR_.uniqid();
            } while (file_exists($uniqidPath));
            file_put_contents($uniqidPath, $field);
            $fd = fopen($uniqidPath, 'r');
        }

        if ($fd === false) {
            return [];
        }

        $tab = fgetcsv($fd, static::MAX_LINE_SIZE, $separator);
        fclose($fd);
        if ($uniqidPath !== false && file_exists($uniqidPath)) {
            @unlink($uniqidPath);
        }

        if (empty($tab) || (!is_array($tab))) {
            return [];
        }

        return $tab;
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function manufacturerImport($offset = false, $limit = false, $validateOnly = false)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $shopIsFeatureActive = Shop::isFeatureActive();
        $regenerate = Tools::getValue('regenerate');
        $forceIds = Tools::getValue('forceIDs');

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->manufacturerImportOne(
                    $info,
                    $shopIsFeatureActive,
                    $regenerate,
                    $forceIds,
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param array $info
     * @param bool $shopIsFeatureActive
     * @param bool $regenerate
     * @param bool $forceIds
     * @param bool $validateOnly
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function manufacturerImportOne($info, $shopIsFeatureActive, $regenerate, $forceIds, $validateOnly = false)
    {
        static::setDefaultValues($info);

        if ($forceIds && isset($info['id']) && (int) $info['id']) {
            $manufacturer = new Manufacturer((int) $info['id']);
        } else {
            if (array_key_exists('id', $info) && (int) $info['id'] && Manufacturer::existsInDatabase((int) $info['id'], 'manufacturer')) {
                $manufacturer = new Manufacturer((int) $info['id']);
            } else {
                $manufacturer = new Manufacturer();
            }
        }

        array_walk($info, [static::class, 'fillInfo'], $manufacturer);

        $res = false;
        if (($fieldError = $manufacturer->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
            ($langFieldError = $manufacturer->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
        ) {
            if ($manufacturer->id && $manufacturer->manufacturerExists($manufacturer->id)) {
                $res = ($validateOnly || $manufacturer->update());
            }
            $manufacturer->force_id = (bool) $forceIds;
            if (!$res) {
                $res = ($validateOnly || $manufacturer->add());
            }

            //copying images of manufacturer
            if (!$validateOnly && !empty($manufacturer->image)) {
                if (!static::copyImg($manufacturer->id, null, $manufacturer->image, static::ENTITY_TYPE_MANUFACTURERS, !$regenerate, $this->warnings)) {
                    $this->warnings[] = $manufacturer->image.' '.$this->l('cannot be copied.');
                }
            }

            if (!$validateOnly && $res) {
                // Associate supplier to group shop
                if ($shopIsFeatureActive && $manufacturer->shop) {
                    Db::getInstance()->delete(
                        'manufacturer_shop',
                        'id_manufacturer = '.(int) $manufacturer->id
                    );
                    $manufacturer->shop = explode($this->multiple_value_separator, $manufacturer->shop);
                    $shops = [];
                    foreach ($manufacturer->shop as $shop) {
                        if (empty($shop)) {
                            continue;
                        }
                        $shop = trim($shop);
                        if (!is_numeric($shop)) {
                            $shop = ShopGroup::getIdByName($shop);
                        }
                        $shops[] = $shop;
                    }
                    $manufacturer->associateTo($shops);
                }
            }
        }

        if (!$res) {
            if (!$validateOnly) {
                $this->errors[] = Db::getInstance()->getMsgError().' '.sprintf(
                    $this->l('%1$s (ID: %2$s) cannot be saved'),
                    (!empty($info['name'])) ? Tools::safeOutput($info['name']) : 'No Name',
                    (!empty($info['id'])) ? Tools::safeOutput($info['id']) : 'No ID'
                );
            }
            if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
            }
        }
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function supplierImport($offset = false, $limit = false, $validateOnly = false)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $shopIsFeatureActive = Shop::isFeatureActive();
        $regenerate = Tools::getValue('regenerate');
        $forceIds = Tools::getValue('forceIDs');

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->supplierImportOne(
                    $info,
                    $shopIsFeatureActive,
                    $regenerate,
                    $forceIds,
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param array $info
     * @param bool $shopIsFeatureActive
     * @param bool $regenerate
     * @param bool $forceIds
     * @param bool $validateOnly
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function supplierImportOne($info, $shopIsFeatureActive, $regenerate, $forceIds, $validateOnly = false)
    {
        static::setDefaultValues($info);

        if ($forceIds && isset($info['id']) && (int) $info['id']) {
            $supplier = new Supplier((int) $info['id']);
        } else {
            if (array_key_exists('id', $info) && (int) $info['id'] && Supplier::existsInDatabase((int) $info['id'], 'supplier')) {
                $supplier = new Supplier((int) $info['id']);
            } else {
                $supplier = new Supplier();
            }
        }

        array_walk($info, [static::class, 'fillInfo'], $supplier);
        if (($fieldError = $supplier->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
            ($langFieldError = $supplier->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
        ) {
            $res = false;
            if ($supplier->id && $supplier->supplierExists($supplier->id)) {
                $res = ($validateOnly || $supplier->update());
            }
            $supplier->force_id = (bool) $forceIds;
            if (!$res) {
                $res = ($validateOnly || $supplier->add());
            }

            //copying images of suppliers
            if (!$validateOnly && !empty($supplier->image)) {
                if (!static::copyImg($supplier->id, null, $supplier->image, static::ENTITY_TYPE_SUPPLIERS, !$regenerate, $this->warnings)) {
                    $this->warnings[] = $supplier->image.' '.$this->l('cannot be copied.');
                }
            }

            if (!$res) {
                $this->errors[] = Db::getInstance()->getMsgError().' '.sprintf(
                    $this->l('%1$s (ID: %2$s) cannot be saved'),
                    (!empty($info['name'])) ? Tools::safeOutput($info['name']) : 'No Name',
                    (!empty($info['id'])) ? Tools::safeOutput($info['id']) : 'No ID'
                );
            } elseif (!$validateOnly) {
                // Associate supplier to group shop
                if ($shopIsFeatureActive && $supplier->shop) {
                    Db::getInstance()->delete(
                        'supplier_shop',
                        '`id_supplier` = '.(int) $supplier->id
                    );
                    $supplier->shop = explode($this->multiple_value_separator, $supplier->shop);
                    $shops = [];
                    foreach ($supplier->shop as $shop) {
                        if (empty($shop)) {
                            continue;
                        }
                        $shop = trim($shop);
                        if (!is_numeric($shop)) {
                            $shop = ShopGroup::getIdByName($shop);
                        }
                        $shops[] = $shop;
                    }
                    $supplier->associateTo($shops);
                }
            }
        } else {
            $this->errors[] = $this->l('Supplier is invalid').' ('.$supplier->name.')';
            $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '');
        }
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function aliasImport($offset = false, $limit = false, $validateOnly = false)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $forceIds = Tools::getValue('forceIDs');

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->aliasImportOne(
                    $info,
                    $forceIds,
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param array $info
     * @param bool $forceIds
     * @param bool $validateOnly
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function aliasImportOne($info, $forceIds, $validateOnly = false)
    {
        static::setDefaultValues($info);

        if ($forceIds && isset($info['id']) && (int) $info['id']) {
            $alias = new Alias((int) $info['id']);
        } else {
            if (array_key_exists('id', $info) && (int) $info['id'] && Alias::existsInDatabase((int) $info['id'], 'alias')) {
                $alias = new Alias((int) $info['id']);
            } else {
                $alias = new Alias();
            }
        }

        array_walk($info, [static::class, 'fillInfo'], $alias);

        $res = false;
        if (($fieldError = $alias->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
            ($langFieldError = $alias->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
        ) {
            if ($alias->id && $alias->aliasExists($alias->id)) {
                $res = ($validateOnly || $alias->update());
            }
            $alias->force_id = (bool) $forceIds;
            if (!$res) {
                $res = ($validateOnly || $alias->add());
            }

            if (!$res) {
                $this->errors[] = Db::getInstance()->getMsgError().' '.sprintf(
                    $this->l('%1$s (ID: %2$s) cannot be saved'),
                    $info['name'],
                    $info['id'] ?? 'null'
                );
            }
        } else {
            $this->errors[] = $this->l('Alias is invalid').' ('.$alias->name.')';
            $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '');
        }
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function storeContactImport($offset = false, $limit = false, $validateOnly = false)
    {
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        $forceIds = Tools::getValue('forceIDs');
        $regenerate = Tools::getValue('regenerate');

        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); $currentLine++) {
            $lineCount++;

            if (count($line) == 1 && $line[0] == null) {
                $this->warnings[] = $this->l('There is an empty row in the file that won\'t be imported.');
                continue;
            }

            $info = static::getMaskedRow($line);

            try {
                $this->storeContactImportOne(
                    $info,
                    Shop::isFeatureActive(),
                    $regenerate,
                    $forceIds,
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param array $info
     * @param bool $shopIsFeatureActive
     * @param bool $regenerate
     * @param bool $forceIds
     * @param bool $validateOnly
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function storeContactImportOne($info, $shopIsFeatureActive, $regenerate, $forceIds, $validateOnly = false)
    {
        static::setDefaultValues($info);

        if ($forceIds && isset($info['id']) && (int) $info['id']) {
            $store = new Store((int) $info['id']);
        } else {
            if (array_key_exists('id', $info) && (int) $info['id'] && Store::existsInDatabase((int) $info['id'], 'store')) {
                $store = new Store((int) $info['id']);
            } else {
                $store = new Store();
            }
        }

        array_walk($info, [static::class, 'fillInfo'], $store);

        if (!empty($store->image)) {
            if (!(static::copyImg($store->id, null, $store->image, 'stores', !$regenerate, $this->warnings))) {
                $this->warnings[] = $store->image.' '.$this->l('cannot be copied.');
            }
        }

        // Handle hours field
        if (isset($info['hours']) && is_string($info['hours'])) {
            // Convert the CSV string to an array using ; as the delimiter
            $hoursArray = explode(';', $info['hours']);
            $store->hours = json_encode($hoursArray);
        } elseif (isset($store->hours) && is_array($store->hours)) {
            $store->hours = json_encode($store->hours);
        }

        if (isset($store->country) && is_numeric($store->country)) {
            if (Country::getNameById(Configuration::get('PS_LANG_DEFAULT'), (int) $store->country)) {
                $store->id_country = (int) $store->country;
            }
        } elseif (is_string($store->country) && !empty($store->country)) {
            if ($idCountry = Country::getIdByName(null, $store->country)) {
                $store->id_country = (int) $idCountry;
            } else {
                $country = new Country();
                $country->active = 1;
                $country->name = static::createMultiLangField($store->country);
                $country->id_zone = 0; // Default zone for country to create
                $country->iso_code = mb_strtoupper(mb_substr($store->country, 0, 2)); // Default iso for country to create
                $country->contains_states = 0; // Default value for country to create
                $langFieldError = $country->validateFieldsLang(static::UNFRIENDLY_ERROR, true);
                if (($fieldError = $country->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $country->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $country->add()
                ) {
                    $store->id_country = (int) $country->id;
                } else {
                    if (!$validateOnly) {
                        $defaultLanguageId = (int) Configuration::get('PS_LANG_DEFAULT');
                        $this->errors[] = sprintf($this->l('%s cannot be saved'), $country->name[$defaultLanguageId]);
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
                    }
                }
            }
        }

        if (isset($store->state) && is_numeric($store->state)) {
            if (State::getNameById((int) $store->state)) {
                $store->id_state = (int) $store->state;
            }
        } elseif (is_string($store->state) && !empty($store->state)) {
            if ($idState = State::getIdByName($store->state)) {
                $store->id_state = (int) $idState;
            } else {
                $state = new State();
                $state->active = 1;
                $state->name = $store->state;
                $state->id_country = isset($country->id) ? (int) $country->id : 0;
                $state->id_zone = 0; // Default zone for state to create
                $state->iso_code = mb_strtoupper(mb_substr($store->state, 0, 2)); // Default iso for state to create
                $state->tax_behavior = 0;
                if (($fieldError = $state->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
                    ($langFieldError = $state->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true &&
                    !$validateOnly && // Do not move this condition: previous tests should be played always, but next ->add() test should not be played in validateOnly mode
                    $state->add()
                ) {
                    $store->id_state = (int) $state->id;
                } else {
                    if (!$validateOnly) {
                        $this->errors[] = sprintf($this->l('%s cannot be saved'), $state->name);
                    }
                    if ($fieldError !== true || isset($langFieldError) && $langFieldError !== true) {
                        $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '').Db::getInstance()->getMsgError();
                    }
                }
            }
        }

        $res = false;
        if (($fieldError = $store->validateFields(static::UNFRIENDLY_ERROR, true)) === true &&
            ($langFieldError = $store->validateFieldsLang(static::UNFRIENDLY_ERROR, true)) === true
        ) {
            if ($store->id && static::storeExists($store->id)) {
                $res = $validateOnly ? $validateOnly : $store->update();
            }
            $store->force_id = (bool) $forceIds;
            if (!$res) {
                $res = $validateOnly ? $validateOnly : $store->add();
            }

            if (!$res) {
                $this->errors[] = Db::getInstance()->getMsgError().' '.sprintf(
                    $this->l('%1$s (ID: %2$s) cannot be saved'),
                    $info['name'],
                    $info['id'] ?? 'null'
                );
            }
        } else {
            $this->errors[] = $this->l('Store is invalid').' ('.$store->name.')';
            $this->errors[] = ($fieldError !== true ? $fieldError : '').(isset($langFieldError) && $langFieldError !== true ? $langFieldError : '');
        }
    }

    /**
     * This method checks if a store exists
     *
     * @param int $idStore Store ID
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    protected static function storeExists($idStore)
    {
        $sql = new DbQuery();
        $sql->select('`id_store`');
        $sql->from('store');
        $sql->where('`id_store` = '.(int) $idStore);

        return (bool) Db::readOnly()->getValue($sql);
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function supplyOrdersImport($offset = false, $limit = false, $validateOnly = false)
    {
        // opens CSV & sets locale
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $forceIds = Tools::getValue('forceIDs');

        // main loop, for each supply orders to import
        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); ++$currentLine) {
            $lineCount++;
            $info = static::getMaskedRow($line);

            $this->supplyOrdersImportOne(
                $info,
                $forceIds,
                $currentLine,
                $validateOnly
            );
        }
        // closes
        $datasource->close();

        return $lineCount;
    }

    /**
     * @param array $info
     * @param bool $forceIds
     * @param int $currentLine
     * @param bool $validateOnly
     * @throws PrestaShopException
     */
    protected function supplyOrdersImportOne($info, $forceIds, $currentLine, $validateOnly = false)
    {
        // sets default values if needed
        static::setDefaultValues($info);

        // if an id is set, instanciates a supply order with this id if possible
        if (array_key_exists('id', $info) && (int) $info['id'] && SupplyOrder::exists((int) $info['id'])) {
            $supplyOrder = new SupplyOrder((int) $info['id']);
        } elseif (array_key_exists('reference', $info) && $info['reference'] && SupplyOrder::exists(pSQL($info['reference']))) {
            $supplyOrder = SupplyOrder::getSupplyOrderByReference(pSQL($info['reference']));
        } else { // new supply order
            $supplyOrder = new SupplyOrder();
        }

        // gets parameters
        $idSupplier = (int) $info['id_supplier'];
        $idLang = (int) $info['id_lang'];
        $idWarehouse = (int) $info['id_warehouse'];
        $idCurrency = (int) $info['id_currency'];
        $reference = pSQL($info['reference']);
        $dateDeliveryExpected = pSQL($info['date_delivery_expected']);
        $discountRate = (float) $info['discount_rate'];

        $error = '';
        // checks parameters
        if (!Supplier::supplierExists($idSupplier)) {
            $error = sprintf($this->l('Supplier ID (%d) is not valid (at line %d).'), $idSupplier, $currentLine + 1);
        }
        if (!Language::getLanguage($idLang)) {
            $error = sprintf($this->l('Lang ID (%d) is not valid (at line %d).'), $idLang, $currentLine + 1);
        }
        if (!Warehouse::exists($idWarehouse)) {
            $error = sprintf($this->l('Warehouse ID (%d) is not valid (at line %d).'), $idWarehouse, $currentLine + 1);
        }
        if (!Currency::getCurrency($idCurrency)) {
            $error = sprintf($this->l('Currency ID (%d) is not valid (at line %d).'), $idCurrency, $currentLine + 1);
        }
        if (empty($supplyOrder->reference) && SupplyOrder::exists($reference)) {
            $error = sprintf($this->l('Reference (%s) already exists (at line %d).'), $reference, $currentLine + 1);
        }
        if (!empty($supplyOrder->reference) && ($supplyOrder->reference != $reference && SupplyOrder::exists($reference))) {
            $error = sprintf($this->l('Reference (%s) already exists (at line %d).'), $reference, $currentLine + 1);
        }
        if (!Validate::isDateFormat($dateDeliveryExpected)) {
            $error = sprintf($this->l('Date format (%s) is not valid (at line %d). It should be: %s.'), $dateDeliveryExpected, $currentLine + 1, $this->l('YYYY-MM-DD'));
        }
        if ($discountRate < 0 || $discountRate > 100) {
            $error = sprintf($this->l('Discount rate (%d) is not valid (at line %d). %s.'), $discountRate, $currentLine + 1, $this->l('Format: Between 0 and 100'));
        }
        if ($supplyOrder->id > 0 && !$supplyOrder->isEditable()) {
            $error = sprintf($this->l('Supply Order (%d) is not editable (at line %d).'), $supplyOrder->id, $currentLine + 1);
        }

        // if no errors, sets supply order
        if (empty($error)) {
            // adds parameters
            $info['id_ref_currency'] = (int) Currency::getDefaultCurrency()->id;
            $info['supplier_name'] = pSQL(Supplier::getNameById($idSupplier));
            if ($supplyOrder->id > 0) {
                $info['id_supply_order_state'] = (int) $supplyOrder->id_supply_order_state;
                $info['id'] = (int) $supplyOrder->id;
            } else {
                $info['id_supply_order_state'] = 1;
            }

            // sets parameters
            array_walk($info, [static::class, 'fillInfo'], $supplyOrder);

            if ((int) $supplyOrder->id && ($supplyOrder->exists((int) $supplyOrder->id) || $supplyOrder->exists($supplyOrder->reference))) {
                $res = ($validateOnly || $supplyOrder->update());
            } else {
                $supplyOrder->force_id = (bool) $forceIds;
                $res = ($validateOnly || $supplyOrder->add());
            }

            // errors
            if (!$res) {
                $this->errors[] = sprintf($this->l('Supply Order could not be saved (at line %d).'), $currentLine + 1);
            }
        } else {
            $this->errors[] = $error;
        }
    }

    /**
     * @param bool $offset
     * @param bool $limit
     * @param array|bool $crossStepsVariables
     * @param bool $validateOnly
     *
     * @return int
     * @throws PrestaShopException
     */
    public function supplyOrdersDetailsImport($offset = false, $limit = false, &$crossStepsVariables = false, $validateOnly = false)
    {
        // opens CSV & sets locale
        $this->receiveTab();
        $datasource = $this->openDataSource($offset);

        static::setLocale();

        $products = [];
        $reset = true;
        if (is_array($crossStepsVariables) && array_key_exists('products', $crossStepsVariables)) {
            $products = $crossStepsVariables['products'];
        }
        if (is_array($crossStepsVariables) && array_key_exists('reset', $crossStepsVariables)) {
            $reset = $crossStepsVariables['reset'];
        }

        $forceIds = Tools::getValue('forceIDs');

        // main loop, for each supply orders details to import
        $lineCount = 0;
        for ($currentLine = 0; ($line = $datasource->getRow()) && (!$limit || $currentLine < $limit); ++$currentLine) {
            $lineCount++;
            $info = static::getMaskedRow($line);

            try {
                $this->supplyOrdersDetailsImportOne(
                    $info,
                    $products, // by ref
                    $reset, // by ref
                    $forceIds,
                    $currentLine,
                    $validateOnly
                );
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        // closes
        $datasource->close();

        if ($crossStepsVariables !== false) {
            $crossStepsVariables['products'] = $products;
            $crossStepsVariables['reset'] = $reset;
        }

        return $lineCount;
    }

    /**
     * @param array $info
     * @param array $products
     * @param bool $reset
     * @param bool $forceIds
     * @param int $currentLine
     * @param bool $validateOnly
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function supplyOrdersDetailsImportOne($info, &$products, &$reset, $forceIds, $currentLine, $validateOnly = false)
    {
        // sets default values if needed
        static::setDefaultValues($info);

        // gets the supply order
        if (array_key_exists('supply_order_reference', $info) && pSQL($info['supply_order_reference']) && SupplyOrder::exists(pSQL($info['supply_order_reference']))) {
            $supplyOrder = SupplyOrder::getSupplyOrderByReference(pSQL($info['supply_order_reference']));
        } else {
            $this->errors[] = sprintf($this->l('Supply Order (%s) could not be loaded (at line %d).'), $info['supply_order_reference'], $currentLine + 1);
        }

        if (empty($this->errors) && isset($supplyOrder)) {
            // sets parameters
            $idProduct = (int) $info['id_product'];
            if (!$info['id_product_attribute']) {
                $info['id_product_attribute'] = 0;
            }
            $idProductAttribute = (int) $info['id_product_attribute'];
            $unitPriceTe = round(
                $info['unit_price_te'],
                _TB_PRICE_DATABASE_PRECISION_
            );
            $quantityExpected = (int) $info['quantity_expected'];
            $discountRate = (float) $info['discount_rate'];
            $taxRate = (float) $info['tax_rate'];

            // checks if one product/attribute is there only once
            if (isset($products[$idProduct][$idProductAttribute])) {
                $this->errors[] = sprintf(
                    $this->l('Product/Attribute (%d/%d) cannot be added twice (at line %d).'),
                    $idProduct,
                    $idProductAttribute,
                    $currentLine + 1
                );
            } else {
                $products[$idProduct][$idProductAttribute] = $quantityExpected;
            }

            // checks parameters
            if (false === ($supplierReference = ProductSupplier::getProductSupplierReference($idProduct, $idProductAttribute, $supplyOrder->id_supplier))) {
                $this->errors[] = sprintf(
                    $this->l('Product (%d/%d) is not available for this order (at line %d).'),
                    $idProduct,
                    $idProductAttribute,
                    $currentLine + 1
                );
            }
            if ($unitPriceTe < 0) {
                $this->errors[] = sprintf($this->l('Unit Price (tax excl.) (%d) is not valid (at line %d).'), $unitPriceTe, $currentLine + 1);
            }
            if ($quantityExpected < 0) {
                $this->errors[] = sprintf($this->l('Quantity Expected (%d) is not valid (at line %d).'), $quantityExpected, $currentLine + 1);
            }
            if ($discountRate < 0 || $discountRate > 100) {
                $this->errors[] = sprintf(
                    $this->l('Discount rate (%d) is not valid (at line %d). %s.'),
                    $discountRate,
                    $currentLine + 1,
                    $this->l('Format: Between 0 and 100')
                );
            }
            if ($taxRate < 0 || $taxRate > 100) {
                $this->errors[] = sprintf(
                    $this->l('Quantity Expected (%d) is not valid (at line %d).'),
                    $taxRate,
                    $currentLine + 1,
                    $this->l('Format: Between 0 and 100')
                );
            }

            // if no errors, sets supply order details
            if (empty($this->errors)) {
                // resets order if needed
                if (!$validateOnly && $reset) {
                    $supplyOrder->resetProducts();
                    $reset = false;
                }

                // creates new product
                $supplyOrderDetail = new SupplyOrderDetail();
                array_walk($info, [static::class, 'fillInfo'], $supplyOrderDetail);

                // sets parameters
                $supplyOrderDetail->id_supply_order = $supplyOrder->id;
                $currency = new Currency($supplyOrder->id_ref_currency);
                $supplyOrderDetail->id_currency = $currency->id;
                $supplyOrderDetail->exchange_rate = $currency->conversion_rate;
                $supplyOrderDetail->supplier_reference = $supplierReference;
                $supplyOrderDetail->name = Product::getProductName($idProduct, $idProductAttribute, $supplyOrder->id_lang);

                // gets ean13 / ref / upc
                $query = new DbQuery();
                $query->select('IFNULL(pa.reference, IFNULL(p.reference, \'\')) as reference');
                $query->select('IFNULL(pa.ean13, IFNULL(p.ean13, \'\')) as ean13');
                $query->select('IFNULL(pa.upc, IFNULL(p.upc, \'\')) as upc');
                $query->from('product', 'p');
                $query->leftJoin('product_attribute', 'pa', 'pa.id_product = p.id_product AND id_product_attribute = '.(int) $idProductAttribute);
                $query->where('p.id_product = '.(int) $idProduct);
                $query->where('p.is_virtual = 0 AND p.cache_is_pack = 0');
                $res = Db::readOnly()->getArray($query);
                $productInfos = $res['0'];

                $supplyOrderDetail->reference = $productInfos['reference'];
                $supplyOrderDetail->ean13 = $productInfos['ean13'];
                $supplyOrderDetail->upc = $productInfos['upc'];
                $supplyOrderDetail->force_id = (bool) $forceIds;
                if (!$validateOnly) {
                    $supplyOrderDetail->add();
                    $supplyOrder->update();
                }
                unset($supplyOrderDetail);
            }
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     * @noinspection PhpUnused
     */
    public function ajaxProcessSaveImportMatchs()
    {
        if ($this->hasEditPermission()) {
            $match = implode('|', Tools::getValue('type_value'));
            $conn = Db::getInstance();
            try {
                $conn->insert(
                    'import_match',
                    [
                        'name'  => pSQL(Tools::getValue('newImportMatchs')),
                        'match' => pSQL($match),
                        'skip'  => pSQL(Tools::getValue('skip')),
                    ],
                    false,
                    Db::INSERT_IGNORE
                );
            } catch (PrestaShopException $e) {
                $this->ajaxDie(json_encode(['hasError' => true, 'error' => $e->getMessage()]));
            }

            $this->ajaxDie(json_encode(['id' => (int) $conn->Insert_ID()]));
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     * @noinspection PhpUnused
     */
    public function ajaxProcessLoadImportMatchs()
    {
        if ($this->hasEditPermission()) {
            try {
                $return = Db::readOnly()->getArray(
                    (new DbQuery())
                        ->select('*')
                        ->from('import_match')
                        ->where('`id_import_match` = '.Tools::getIntValue('idImportMatchs'))
                );
                $this->ajaxDie(json_encode([
                    'id' => $return[0]['id_import_match'],
                    'matchs' => $return[0]['match'],
                    'skip' => $return[0]['skip']
                ]));
            } catch (PrestaShopException $e) {
                $this->ajaxDie(json_encode([
                    'hasError' => true,
                    'error' => $e->getMessage()
                ]));
            }
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     * @noinspection PhpUnused
     */
    public function ajaxProcessDeleteImportMatchs()
    {
        if ($this->hasEditPermission()) {
            try {
                Db::getInstance()->delete(
                    'import_match',
                    '`id_import_match` = '.Tools::getIntValue('idImportMatchs'),
                    false
                );
            } catch (PrestaShopException $e) {
                $this->ajaxDie(json_encode(['hasError' => true, 'error' => $e->getMessage()]));
            }
            die;
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     * @noinspection PhpUnused
     */
    public function ajaxProcessImport()
    {
        $offset = Tools::getIntValue('offset');
        $limit = Tools::getIntValue('limit');
        $validateOnly = (Tools::getIntValue('validateOnly') === 1);
        $moreStep = Tools::getIntValue('moreStep');

        $results = [];
        $this->importByGroups($offset, $limit, $results, $validateOnly, $moreStep);

        // Retrieve errors/warnings if any
        if (count($this->errors) > 0) {
            $results['errors'] = $this->errors;
        }
        if (count($this->warnings) > 0) {
            $results['warnings'] = $this->warnings;
        }
        if (count($this->informations) > 0) {
            $results['informations'] = $this->informations;
        }

        $this->ajaxDie(json_encode($results));
    }

    /**
     * @return void
     *
     * @throws SmartyException
     */
    public function initModal()
    {
        parent::initModal();
        $modalContent = $this->context->smarty->fetch('controllers/import/modal_import_progress.tpl');
        $this->modals[] = [
            'modal_id'      => 'importProgress',
            'modal_class'   => 'modal-md',
            'modal_title'   => $this->l('Importing...'),
            'modal_content' => $modalContent,
        ];
    }

    /**
     * @return array
     *
     * @throws PrestaShopException
     */
    protected function getRegisteredDataSources()
    {
        if (is_null($this->registeredDataSources)) {
            $this->registeredDataSources = [
                'buildin-csv' => [
                    'name' => $this->l('Build-in CSV import'),
                    'extensions' => [ 'csv' ],
                    'constructor' => [static::class, 'createCsvDataSource']
                ]
            ];

            $result = Hook::getResponses('actionRegisterImportDataSource');
            foreach ($result as $moduleId => $mod) {
                if (! isset($mod['extensions']) || ! is_array($mod['extensions'])) {
                    throw new PrestaShopException('Module '. $moduleId . ' returned invalid data for hook actionRegisterImportDataSource: missing "extensions"');
                }
                if (! isset($mod['constructor']) || ! is_callable($mod['constructor'])) {
                    throw new PrestaShopException('Module '. $moduleId . ' returned invalid data for hook actionRegisterImportDataSource: missing "constructor"');
                }
                if (! isset($mod['name'])) {
                    throw new PrestaShopException('Module '. $moduleId . ' returned invalid data for hook actionRegisterImportDataSource: missing "name"');
                }
                $this->registeredDataSources['module-' . $moduleId] = [
                    'name' => $mod['name'],
                    'extensions' => $mod['extensions'],
                    'constructor' => $mod['constructor'],
                ];
            }
        }
        return $this->registeredDataSources;
    }

    /**
     * Return list of supported file extensions
     *
     * @return string[]
     * @throws PrestaShopException
     */
    protected function getFileExtensions()
    {
        $extensions = [];
        foreach ($this->getRegisteredDataSources() as $id => $mod) {
            foreach ($mod['extensions'] as $ext) {
                if (! array_key_exists($ext, $extensions)) {
                    $extensions[$ext] = [];
                }
                if (! in_array($id, $extensions[$ext])) {
                    $extensions[$ext][] = $id;
                }
            }
        }
        return $extensions;
    }

    /**
     * @param string $filename
     * @param array $params
     *
     * @return DataSourceInterface
     * @throws PrestaShopException
     */
    public static function createCsvDataSource($filename, $params)
    {
        return new CSVDataSource($filename, $params['separator'] ?? ',');
    }

    /**
     * Returns currently selected entity type
     *
     * @return string
     */
    protected function getSelectedEntity()
    {
        // first, consider 'entity' parameter
        $entityType = Tools::getValue('entity');
        if ($entityType && $this->isValidEntity($entityType)) {
            return $this->updateSelectedEntity($entityType);
        }

        // then consider 'import_type' parameter. This is used by drilldown links
        $importType = Tools::getValue('import_type');
        if ($importType && $this->isValidEntity($importType)) {
            return $this->updateSelectedEntity($importType);
        }

        // take last selected entity type
        if (isset($this->context->cookie->entitySelected)) {
            $cookieEntity = $this->context->cookie->entitySelected;
            if ($this->isValidEntity($cookieEntity)) {
                return $cookieEntity;
            }
        }

        // fallback to 'products' entity type
        return $this->updateSelectedEntity(static::ENTITY_TYPE_PRODUCTS);
    }

    /**
     * Updates currently selected entity type - saves the information into cookie
     *
     * @param string $entityType
     *
     * @return string
     */
    protected function updateSelectedEntity($entityType)
    {
        $cookie = $this->context->cookie;
        if (isset($cookie->entitySelected) && $cookie->entitySelected !== $entityType) {
            $cookie->entitySelected = $entityType;
        } else {
            $cookie->entitySelected = $entityType;
        }
        return $entityType;
    }

    /**
     * Returns true, if $entityType is known
     *
     * @param string $entityType
     *
     * @return bool
     */
    protected function isValidEntity($entityType)
    {
        return isset($this->entities[$entityType]);
    }

    /**
     * Returns all registered import entity types
     *
     * @return ImportEntityType[]
     * @throws PrestaShopException
     */
    protected static function getEntityTypes()
    {
        static $entityTypes = null;
        if ($entityTypes === null) {
            $entityTypes = [];
            $result = Hook::getResponses('actionRegisterImportEntities');
            foreach ($result as $moduleId => $mod) {
                if (!is_array($mod)) {
                    $mod = ['default' => $mod];
                }
                foreach ($mod as $entityTypeId => $entityType) {
                    if ($entityType instanceof ImportEntityType) {
                        $entityTypes[$moduleId . ':' . $entityTypeId] = $entityType;
                    } else {
                        trigger_error("Module $moduleId registered invalid import entity type");
                    }
                }
            }
        }
        return $entityTypes;
    }

    /**
     * Returns true, if import entity type exits
     *
     * @param string $entityTypeId
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected static function hasEntityType(string $entityTypeId)
    {
        return array_key_exists($entityTypeId, static::getEntityTypes());
    }

    /**
     * Return entity type by ID
     *
     * @param string $entityTypeId
     *
     * @return ImportEntityType
     * @throws PrestaShopException
     */
    protected static function getEntityType(string $entityTypeId):ImportEntityType
    {
        $entityTypes = static::getEntityTypes();
        if (! array_key_exists($entityTypeId, $entityTypes)) {
            throw new PrestaShopException("Entity type $entityTypeId not found");
        }
        return $entityTypes[$entityTypeId];
    }

    /**
     * Return list of entity types that support truncate
     *
     * @return string[]
     * @throws PrestaShopException
     */
    protected function getTruncatableEntityTypes()
    {
        $truncatable = [
            AdminImportController::ENTITY_TYPE_CATEGORIES,
            AdminImportController::ENTITY_TYPE_PRODUCTS,
            AdminImportController::ENTITY_TYPE_COMBINATIONS,
            AdminImportController::ENTITY_TYPE_CUSTOMERS,
            AdminImportController::ENTITY_TYPE_ADDRESSES,
            AdminImportController::ENTITY_TYPE_MANUFACTURERS,
            AdminImportController::ENTITY_TYPE_SUPPLIERS,
            AdminImportController::ENTITY_TYPE_ALIAS
        ];
        foreach (static::getEntityTypes() as $key => $entityType) {
            if ($entityType->supportTruncate()) {
                $truncatable[] = $key;
            }
        }
        return $truncatable;
    }
}
