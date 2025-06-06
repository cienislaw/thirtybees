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

use CoreUpdater\TableSchema;
use Thirtybees\Core\InitializationCallback;

/**
 * Class CategoryCore
 */
class CategoryCore extends ObjectModel implements InitializationCallback
{
    /**
     * @var array Object model definition
     */
    public static $definition = [
        'table'          => 'category',
        'primary'        => 'id_category',
        'multilang'      => true,
        'multilang_shop' => true,
        'fields'         => [
            'id_parent'        => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbNullable' => false],
            'id_shop_default'  => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'dbDefault' => '1'],
            'level_depth'      => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbType' => 'tinyint(3) unsigned', 'dbDefault' => '0'],
            'nleft'            => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbDefault' => '0'],
            'nright'           => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'dbDefault' => '0'],
            'active'           => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true, 'dbDefault' => '0', 'shop' => true],
            'display_from_sub' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbDefault' => '0', 'shop' => true],
            'date_add'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false, 'shop' => true],
            'date_upd'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'dbNullable' => false, 'shop' => true],
            'position'         => ['type' => self::TYPE_INT, 'dbDefault' => '0', 'shop' => true],
            'is_root_category' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'dbType' => 'tinyint(1)', 'dbDefault' => '0'],
            /* Lang fields */
            'name'             => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCatalogName', 'required' => true, 'size' => 128],
            'description'      => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => ObjectModel::SIZE_TEXT],
            'additional_description' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => ObjectModel::SIZE_TEXT],
            'link_rewrite'     => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isLinkRewrite', 'required' => true, 'size' => 128],
            'meta_title'       => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 128],
            'meta_keywords'    => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 255],
            'meta_description' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 255],
        ],
        'keys' => [
            'category' => [
                'activenleft'      => ['type' => ObjectModel::KEY, 'columns' => ['active', 'nleft']],
                'activenright'     => ['type' => ObjectModel::KEY, 'columns' => ['active', 'nright']],
                'category_parent'  => ['type' => ObjectModel::KEY, 'columns' => ['id_parent']],
                'level_depth'      => ['type' => ObjectModel::KEY, 'columns' => ['level_depth']],
                'nleftrightactive' => ['type' => ObjectModel::KEY, 'columns' => ['nleft', 'nright', 'active']],
                'nright'           => ['type' => ObjectModel::KEY, 'columns' => ['nright']],
            ],
            'category_lang' => [
                'primary'       => ['type' => ObjectModel::PRIMARY_KEY, 'columns' => ['id_category', 'id_shop', 'id_lang']],
                'category_name' => ['type' => ObjectModel::KEY, 'columns' => ['name']],
            ],
        ],
        'images' => [
            ImageEntity::ENTITY_TYPE_CATEGORIES => [
                'inputName' => 'image',
                'path' => _PS_CAT_IMG_DIR_,
            ],
            ImageEntity::ENTITY_TYPE_CATEGORIES_THUMB => [
                'inputName' => 'thumb',
                'displayName' => 'Categories Thumbnails',
                'path' => _PS_CAT_IMG_DIR_.'thumb/',
            ],
        ],
    ];
    /**
     * @var array
     */
    protected static $_links = [];
    /** @var int category ID */
    public $id_category;
    /** @var string|string[] Name */
    public $name;
    /** @var bool Status for display */
    public $active = 1;
    /** @var bool Status for displaying subcategory products */
    public $display_from_sub = 1;
    /** @var int category position */
    public $position;
    /** @var string|string[] Description */
    public $description;
    /** @var string|string[] Additional description */
    public $additional_description;
    /** @var int Parent category ID */
    public $id_parent;
    /** @var int default Category id */
    public $id_category_default;
    /** @var int Parents number */
    public $level_depth;
    /** @var int Nested tree model "left" value */
    public $nleft;
    /** @var int Nested tree model "right" value */
    public $nright;
    /** @var string|string[] string used in rewrited URL */
    public $link_rewrite;
    /** @var string|string[] Meta title */
    public $meta_title;
    /** @var string|string[] Meta keywords */
    public $meta_keywords;
    /** @var string|string[] Meta description */
    public $meta_description;
    /** @var string Object creation date */
    public $date_add;
    /** @var string Object last modification date */
    public $date_upd;
    /** @var bool is Category Root */
    public $is_root_category;
    /** @var int */
    public $id_shop_default;
    /**
     * @var array
     */
    public $groupBox = null;

    /** @var string id_image is the category ID when an image exists and false otherwise */
    public $id_image = false;

    /**
     * @var array Webservice parameters
     */
    protected $webserviceParameters = [
        'objectsNodeName' => 'categories',
        'hidden_fields'   => ['nleft', 'nright', 'groupBox'],
        'fields'          => [
            'id_parent'             => ['xlink_resource' => 'categories'],
            'level_depth'           => ['setter' => false],
            'nb_products_recursive' => ['getter' => 'getWsNbProductsRecursive', 'setter' => false],
        ],
        'associations'    => [
            'categories' => ['getter' => 'getChildrenWs', 'resource' => 'category',],
            'products'   => ['getter' => 'getProductsWs', 'resource' => 'product',],
        ],
    ];

    /**
     * CategoryCore constructor.
     *
     * @param int|null $idCategory
     * @param int|null $idLang
     * @param int|null $idShop
     *
     * @throws PrestaShopException
     */
    public function __construct($idCategory = null, $idLang = null, $idShop = null)
    {
        parent::__construct($idCategory, $idLang, $idShop);
        if ($this->id && ImageManager::getSourceImage(_PS_CAT_IMG_DIR_, $this->id)) {
            $this->id_image = $this->id;
        }
        $this->image_dir = _PS_CAT_IMG_DIR_;
    }

    /**
     * @param array[] $categories
     * @param array $current
     * @param int|null $idCategory
     * @param int $idSelected
     *
     * @throws PrestaShopException
     */
    public static function recurseCategory($categories, $current, $idCategory = null, $idSelected = 1)
    {
        if (!$idCategory) {
            $idCategory = (int) Configuration::get('PS_ROOT_CATEGORY');
        }

        echo '<option value="'.$idCategory.'"'.(($idSelected == $idCategory) ? ' selected="selected"' : '').'>'.
            str_repeat('&nbsp;', $current['infos']['level_depth'] * 5).stripslashes($current['infos']['name']).'</option>';
        if (isset($categories[$idCategory])) {
            foreach (array_keys($categories[$idCategory]) as $key) {
                Category::recurseCategory($categories, $categories[$idCategory][$key], $key, $idSelected);
            }
        }
    }

    /**
     * Return available categories
     *
     * @param bool $idLang Language ID
     * @param bool $active return only active categories
     *
     * @param bool $order
     * @param string $sqlFilter
     * @param string $sqlSort
     * @param string $sqlLimit
     *
     * @return array Categories
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getCategories($idLang = false, $active = true, $order = true, $sqlFilter = '', $sqlSort = '', $sqlLimit = '')
    {
        $result = Db::readOnly()->getArray(
            '
			SELECT *
			FROM `'._DB_PREFIX_.'category` c
			'.Shop::addSqlAssociation('category', 'c').'
			LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON c.`id_category` = cl.`id_category`'.Shop::addSqlRestrictionOnLang('cl').'
			WHERE 1 '.$sqlFilter.' '.($idLang ? 'AND `id_lang` = '.(int) $idLang : '').'
			'.static::getActiveColumnCondition($active, true).'
			'.(!$idLang ? 'GROUP BY c.id_category' : '').'
			'.($sqlSort != '' ? $sqlSort : 'ORDER BY c.`level_depth` ASC, category_shop.`position` ASC').'
			'.($sqlLimit != '' ? $sqlLimit : '')
        );

        if (!$order) {
            return $result;
        }

        $categories = [];
        foreach ($result as $row) {
            $categories[$row['id_parent']][$row['id_category']]['infos'] = $row;
        }


        return $categories;
    }

    /**
     * Helper method to return active column condition
     *
     * @param bool $active
     * @param bool $useShopRestriction
     * @return string
     */
    protected static function getActiveColumnCondition($active, $useShopRestriction)
    {
        if ($active) {
            if ($useShopRestriction) {
                return 'AND category_shop.`active` = 1';
            } else {
                return 'AND EXISTS(SELECT 1 FROM ' . _DB_PREFIX_ . 'category_shop cs WHERE cs.id_category = c.id_category AND cs.active = 1)';
            }
        }
        return '';
    }

    /**
     * @param int|null $rootCategory
     * @param bool $idLang
     * @param bool $active
     * @param array|null $groups
     * @param bool $useShopRestriction
     * @param string $sqlFilter
     * @param string $sqlSort
     * @param string $sqlLimit
     *
     * @return array|false
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getAllCategoriesName(
        $rootCategory = null,
        $idLang = false,
        $active = true,
        $groups = null,
        $useShopRestriction = true,
        $sqlFilter = '',
        $sqlSort = '',
        $sqlLimit = ''
    ) {
        if (isset($groups) && Group::isFeatureActive() && !is_array($groups)) {
            $groups = (array) $groups;
        }

        $cacheId = 'Category::getAllCategoriesName_'.md5(
            (int) $rootCategory.(int) $idLang.(int) $active.(int) $useShopRestriction
            .(isset($groups) && Group::isFeatureActive() ? implode('', $groups) : '')
        );

        if (!Cache::isStored($cacheId)) {
            $result = Db::readOnly()->getArray(
                '
				SELECT c.id_category, cl.name
				FROM `'._DB_PREFIX_.'category` c
				'.($useShopRestriction ? Shop::addSqlAssociation('category', 'c') : '').'
				LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON c.`id_category` = cl.`id_category`'.Shop::addSqlRestrictionOnLang('cl').'
				'.(isset($groups) && Group::isFeatureActive() ? 'LEFT JOIN `'._DB_PREFIX_.'category_group` cg ON c.`id_category` = cg.`id_category`' : '').'
				'.(isset($rootCategory) ? 'RIGHT JOIN `'._DB_PREFIX_.'category` c2 ON c2.`id_category` = '.(int) $rootCategory.' AND c.`nleft` >= c2.`nleft` AND c.`nright` <= c2.`nright`' : '').'
				WHERE '.($sqlFilter ? $sqlFilter : '1').' '.($idLang ? 'AND `id_lang` = '.(int) $idLang : '').'
				'.static::getActiveColumnCondition($active, $useShopRestriction).'
				'.(isset($groups) && Group::isFeatureActive() ? ' AND cg.`id_group` IN ('.implode(',', $groups).')' : '').'
				'.(!$idLang || (isset($groups) && Group::isFeatureActive()) ? ' GROUP BY c.`id_category`' : '').'
				'.($sqlSort != '' ? $sqlSort : ' ORDER BY c.`level_depth` ASC').'
				'.($sqlSort == '' && $useShopRestriction ? ', category_shop.`position` ASC' : '').'
				'.($sqlLimit != '' ? $sqlLimit : '')
            );

            Cache::store($cacheId, $result);
        } else {
            $result = Cache::retrieve($cacheId);
        }

        return $result;
    }

    /**
     * @param int|null $rootCategory
     * @param bool $idLang
     * @param bool $active
     * @param array|null $groups
     * @param bool $useShopRestriction
     * @param string $sqlFilter
     * @param string $sqlSort
     * @param string $sqlLimit
     *
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getNestedCategories(
        $rootCategory = null,
        $idLang = false,
        $active = true,
        $groups = null,
        $useShopRestriction = true,
        $sqlFilter = '',
        $sqlSort = '',
        $sqlLimit = ''
    ) {
        if (isset($groups) && Group::isFeatureActive() && !is_array($groups)) {
            $groups = (array) $groups;
        }

        $cacheId = 'Category::getNestedCategories_'.md5(
                (int) $rootCategory.(int) $idLang.(int) $active.(int) $useShopRestriction
                .(isset($groups) && Group::isFeatureActive() ? implode('', $groups) : '')
            );

        if (!Cache::isStored($cacheId)) {
            $result = Db::readOnly()->getArray(
                '
				SELECT c.*, cl.*
				FROM `'._DB_PREFIX_.'category` c
				'.($useShopRestriction ? Shop::addSqlAssociation('category', 'c') : '').'
				LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON c.`id_category` = cl.`id_category`'.Shop::addSqlRestrictionOnLang('cl').'
				'.(isset($groups) && Group::isFeatureActive() ? 'LEFT JOIN `'._DB_PREFIX_.'category_group` cg ON c.`id_category` = cg.`id_category`' : '').'
				'.(isset($rootCategory) ? 'RIGHT JOIN `'._DB_PREFIX_.'category` c2 ON c2.`id_category` = '.(int) $rootCategory.' AND c.`nleft` >= c2.`nleft` AND c.`nright` <= c2.`nright`' : '').'
				WHERE 1 '.$sqlFilter.' '.($idLang ? 'AND `id_lang` = '.(int) $idLang : '').'
				'.static::getActiveColumnCondition($active, $useShopRestriction).'
				'.(isset($groups) && Group::isFeatureActive() ? ' AND cg.`id_group` IN ('.implode(',', $groups).')' : '').'
				'.(!$idLang || (isset($groups) && Group::isFeatureActive()) ? ' GROUP BY c.`id_category`' : '').'
				'.($sqlSort != '' ? $sqlSort : ' ORDER BY c.`level_depth` ASC').'
				'.($sqlSort == '' && $useShopRestriction ? ', category_shop.`position` ASC' : '').'
				'.($sqlLimit != '' ? $sqlLimit : '')
            );

            $categories = [];
            $buff = [];

            if (!isset($rootCategory)) {
                $rootCategory = Category::getRootCategory()->id;
            }

            foreach ($result as $row) {
                $current = &$buff[$row['id_category']];
                $current = $row;

                if ($row['id_category'] == $rootCategory) {
                    $categories[$row['id_category']] = &$current;
                } else {
                    $buff[$row['id_parent']]['children'][$row['id_category']] = &$current;
                }
            }

            Cache::store($cacheId, $categories);
        } else {
            $categories = Cache::retrieve($cacheId);
        }

        return $categories;
    }

    /**
     * @param int|null $idLang
     * @param Shop|null $shop
     *
     * @return Category
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getRootCategory($idLang = null, ?Shop $shop = null)
    {
        $context = Context::getContext();
        if (is_null($idLang)) {
            $idLang = $context->language->id;
        }
        if (!$shop) {
            if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
                $shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            } else {
                $shop = $context->shop;
            }
        } else {
            return new Category($shop->getCategory(), $idLang);
        }
        $isMoreThanOneRootCategory = count(Category::getCategoriesWithoutParent()) > 1;
        if (Shop::isFeatureActive() && $isMoreThanOneRootCategory && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $category = Category::getTopCategory($idLang);
        } else {
            $category = new Category($shop->getCategory(), $idLang);
        }

        return $category;
    }

    /**
     * @return array|false
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getCategoriesWithoutParent()
    {
        $cacheId = 'Category::getCategoriesWithoutParent_'.(int) Context::getContext()->language->id;
        if (!Cache::isStored($cacheId)) {
            $result = Db::readOnly()->getArray(
                '
			SELECT DISTINCT c.*
			FROM `'._DB_PREFIX_.'category` c
			LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.`id_category` = cl.`id_category` AND cl.`id_lang` = '.(int) Context::getContext()->language->id.')
			WHERE `level_depth` = 1'
            );
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * @param int|null $idLang
     *
     * @return Category
     *
     * @throws PrestaShopException
     */
    public static function getTopCategory($idLang = null)
    {
        if (is_null($idLang)) {
            $idLang = (int) Context::getContext()->language->id;
        }
        $cacheId = 'Category::getTopCategory_'.(int) $idLang;
        if (!Cache::isStored($cacheId)) {
            $idCategory = (int) Db::readOnly()->getValue(
                (new DbQuery())
                    ->select('`id_category`')
                    ->from('category')
                    ->where('`id_parent` = 0')
            );
            $category = new Category($idCategory, $idLang);
            Cache::store($cacheId, $category);

            return $category;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * @param int $idLang
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getSimpleCategories($idLang)
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('c.`id_category`, cl.`name`')
                ->from('category', 'c')
                ->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category` '.Shop::addSqlRestrictionOnLang('cl'))
                ->join(Shop::addSqlAssociation('category', 'c'))
                ->where('cl.`id_lang` = '.(int) $idLang)
                ->where('c.`id_category` != '.Configuration::get('PS_ROOT_CATEGORY'))
                ->groupBy('c.`id_category`')
                ->orderBy('c.`id_category`, category_shop.`position`')
        );
    }

    /**
     * Return main categories
     *
     * @param int $idLang Language ID
     * @param bool $active return only active categories
     *
     * @param bool $idShop
     *
     * @return array categories
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getHomeCategories($idLang, $active = true, $idShop = false)
    {
        return static::getChildren(Configuration::get('PS_HOME_CATEGORY'), $idLang, $active, $idShop);
    }

    /**
     * @param int $idParent
     * @param int $idLang
     * @param bool $active
     * @param bool $idShop
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getChildren($idParent, $idLang, $active = true, $idShop = false)
    {
        $cacheId = 'Category::getChildren_'.(int) $idParent.'-'.(int) $idLang.'-'.(bool) $active.'-'.(int) $idShop;
        if (!Cache::isStored($cacheId)) {
            $query = 'SELECT c.`id_category`, cl.`name`, cl.`link_rewrite`, category_shop.`id_shop`
			FROM `'._DB_PREFIX_.'category` c
			LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.`id_category` = cl.`id_category`'.Shop::addSqlRestrictionOnLang('cl').')
			'.Shop::addSqlAssociation('category', 'c').'
			WHERE `id_lang` = '.(int) $idLang.'
			AND c.`id_parent` = '.(int) $idParent.'
			'.static::getActiveColumnCondition($active, true).'
			GROUP BY c.`id_category`
			ORDER BY category_shop.`position` ASC';
            $result = Db::readOnly()->getArray($query);
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * @param int $idParent
     * @param int $idLang
     * @param bool $active
     * @param bool $idShop
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function hasChildren($idParent, $idLang, $active = true, $idShop = false)
    {
        $cacheId = 'Category::hasChildren_'.(int) $idParent.'-'.(int) $idLang.'-'.(bool) $active.'-'.(int) $idShop;
        if (!Cache::isStored($cacheId)) {
            $query = 'SELECT c.id_category, "" AS name
			FROM `'._DB_PREFIX_.'category` c
			LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.`id_category` = cl.`id_category`'.Shop::addSqlRestrictionOnLang('cl').')
			'.Shop::addSqlAssociation('category', 'c').'
			WHERE `id_lang` = '.(int) $idLang.'
			AND c.`id_parent` = '.(int) $idParent.'
			'.static::getActiveColumnCondition($active, true).'
			LIMIT 1';
            $result = Db::readOnly()->getArray($query);
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * This method allow to return children categories with the number of sub children selected for a product
     *
     * @param int $idParent
     * @param array $selectedCat
     * @param int $idLang
     * @param Shop|null $shop
     * @param bool $useShopContext
     *
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getChildrenWithNbSelectedSubCat($idParent, $selectedCat, $idLang, ?Shop $shop = null, $useShopContext = true)
    {
        if (!$shop) {
            $shop = Context::getContext()->shop;
        }

        $idShop = $shop->id ? $shop->id : Configuration::get('PS_SHOP_DEFAULT');
        $selectedCat = explode(',', str_replace(' ', '', $selectedCat));
        $sql = '
		SELECT c.`id_category`, c.`level_depth`, cl.`name`,
		IF((
			SELECT COUNT(*)
			FROM `'._DB_PREFIX_.'category` c2
			WHERE c2.`id_parent` = c.`id_category`
		) > 0, 1, 0) AS has_children,
		'.($selectedCat ? '(
			SELECT count(c3.`id_category`)
			FROM `'._DB_PREFIX_.'category` c3
			WHERE c3.`nleft` > c.`nleft`
			AND c3.`nright` < c.`nright`
			AND c3.`id_category`  IN ('.implode(',', array_map('intval', $selectedCat)).')
		)' : '0').' AS nbSelectedSubCat
		FROM `'._DB_PREFIX_.'category` c
		LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.`id_category` = cl.`id_category` '.Shop::addSqlRestrictionOnLang('cl', $idShop).')
		LEFT JOIN `'._DB_PREFIX_.'category_shop` cs ON (c.`id_category` = cs.`id_category` AND cs.`id_shop` = '.(int) $idShop.')
		WHERE `id_lang` = '.(int) $idLang.'
		AND c.`id_parent` = '.(int) $idParent;
        if (Shop::getContext() == Shop::CONTEXT_SHOP && $useShopContext) {
            $sql .= ' AND cs.`id_shop` = '.(int) $shop->id;
        }
        if (!Shop::isFeatureActive() || Shop::getContext() == Shop::CONTEXT_SHOP && $useShopContext) {
            $sql .= ' ORDER BY cs.`position` ASC';
        }

        return Db::readOnly()->getArray($sql);
    }

    /**
     * Copy products from a category to another
     *
     * @param int $idOld Source category ID
     * @param bool $idNew Destination category ID
     *
     * @return bool Duplication result
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function duplicateProductCategories($idOld, $idNew)
    {
        $sql = 'SELECT `id_category`
				FROM `'._DB_PREFIX_.'category_product`
				WHERE `id_product` = '.(int) $idOld;
        $result = Db::readOnly()->getArray($sql);

        if ($result) {
            $row = [];
            foreach ($result as $i) {
                $row[] = '('.implode(
                        ', ', [
                            (int) $idNew, $i['id_category'], '(SELECT tmp.max + 1 FROM (
					SELECT MAX(cp.`position`) AS max
					FROM `'._DB_PREFIX_.'category_product` cp
					WHERE cp.`id_category`='.(int) $i['id_category'].') AS tmp)',
                        ]
                    ).')';
            }

            return Db::getInstance()->execute(
                '
                INSERT IGNORE INTO `' . _DB_PREFIX_ . 'category_product` (`id_product`, `id_category`, `position`)
                VALUES ' . implode(',', $row)
            );
        }

        return true;

    }

    /**
     * Check if category can be moved in another one.
     * The category cannot be moved in a child category.
     *
     * @param int $idCategory current category
     * @param int $idParent Parent candidate
     *
     * @return bool Parent validity
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function checkBeforeMove($idCategory, $idParent)
    {
        if ($idCategory == $idParent) {
            return false;
        }
        if ($idParent == Configuration::get('PS_HOME_CATEGORY')) {
            return true;
        }
        $i = (int) $idParent;

        while (42) {
            $result = Db::readOnly()->getRow('SELECT `id_parent` FROM `'._DB_PREFIX_.'category` WHERE `id_category` = '.(int) $i);
            if (!isset($result['id_parent'])) {
                return false;
            }
            if ($result['id_parent'] == $idCategory) {
                return false;
            }
            if ($result['id_parent'] == Configuration::get('PS_HOME_CATEGORY')) {
                return true;
            }
            $i = $result['id_parent'];
        }
    }

    /**
     * @param int $idCategory
     * @param int $idLang
     *
     * @return bool|mixed
     *
     * @throws PrestaShopException
     */
    public static function getLinkRewrite($idCategory, $idLang)
    {
        if (!Validate::isUnsignedId($idCategory) || !Validate::isUnsignedId($idLang)) {
            return false;
        }

        if (!isset(static::$_links[$idCategory.'-'.$idLang])) {
            static::$_links[$idCategory.'-'.$idLang] = Db::readOnly()->getValue(
                '
			SELECT cl.`link_rewrite`
			FROM `'._DB_PREFIX_.'category_lang` cl
			WHERE `id_lang` = '.(int) $idLang.'
			'.Shop::addSqlRestrictionOnLang('cl').'
			AND cl.`id_category` = '.(int) $idCategory
            );
        }

        return static::$_links[$idCategory.'-'.$idLang];
    }

    /**
     * Search with Pathes for categories
     *
     * @param int $idLang Language ID
     * @param string $path of category
     * @param bool $objectToCreate a category
     *                                 * @param bool $methodToCreate a category
     *
     * @return array Corresponding categories
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function searchByPath($idLang, $path, $objectToCreate = false, $methodToCreate = false)
    {
        $categories = explode('/', trim($path));
        $category = $idParentCategory = false;

        if (is_array($categories) && count($categories)) {
            foreach ($categories as $categoryName) {
                if ($idParentCategory) {
                    $category = Category::searchByNameAndParentCategoryId($idLang, $categoryName, $idParentCategory);
                } else {
                    $category = Category::searchByName($idLang, $categoryName, true, true);
                }

                if (!$category && $objectToCreate && $methodToCreate) {
                    call_user_func_array([$objectToCreate, $methodToCreate], [$idLang, $categoryName, $idParentCategory]);
                    $category = Category::searchByPath($idLang, $categoryName);
                }
                if (isset($category['id_category']) && $category['id_category']) {
                    $idParentCategory = (int) $category['id_category'];
                }
            }
        }

        return $category;
    }

    /**
     * Retrieve category by name and parent category id
     *
     * @param int $idLang Language ID
     * @param string $categoryName Searched category name
     * @param int $idParentCategory parent category ID
     *
     * @return array|false Corresponding category
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function searchByNameAndParentCategoryId($idLang, $categoryName, $idParentCategory)
    {
        return Db::readOnly()->getRow(
            '
		SELECT c.*, cl.*
		FROM `'._DB_PREFIX_.'category` c
		LEFT JOIN `'._DB_PREFIX_.'category_lang` cl
			ON (c.`id_category` = cl.`id_category`
			AND `id_lang` = '.(int) $idLang.Shop::addSqlRestrictionOnLang('cl').')
		WHERE `name` = \''.pSQL($categoryName).'\'
			AND c.`id_category` != '.(int) Configuration::get('PS_HOME_CATEGORY').'
			AND c.`id_parent` = '.(int) $idParentCategory
        );
    }

    /**
     * Light back office search for categories
     *
     * @param int $idLang Language ID
     * @param string $query Searched string
     * @param bool $unrestricted allows search without lang and includes first category and exact match
     * @param bool $skipCache
     *
     * @return array Corresponding categories
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public static function searchByName($idLang, $query, $unrestricted = false, $skipCache = false)
    {
        if ($unrestricted === true) {
            $key = 'Category::searchByName_'.$query;
            if ($skipCache || !Cache::isStored($key)) {
                $categories = Db::readOnly()->getRow(
                    (new DbQuery())
                        ->select('c.*, cl.*')
                        ->from('category', 'c')
                        ->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category` '.Shop::addSqlRestrictionOnLang('cl'))
                        ->where('`name` = \''.pSQL($query).'\'')
                );
                if (!$skipCache) {
                    Cache::store($key, $categories);
                }

                return $categories;
            }

            return Cache::retrieve($key);
        } else {
            return Db::readOnly()->getArray(
                (new DbQuery())
                    ->select('c.*, cl.*')
                    ->from('category', 'c')
                    ->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category` AND `id_lang` = '.(int) $idLang.' '.Shop::addSqlRestrictionOnLang('cl'))
                    ->where('`name` LIKE \'%'.pSQL($query).'%\'')
                    ->where('c.`id_category` != '.(int) Configuration::get('PS_HOME_CATEGORY'))
            );
        }
    }

    /**
     * Specify if a category already in base
     *
     * @param int $idCategory Category id
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function categoryExists($idCategory)
    {
        $row = Db::readOnly()->getRow(
            (new DbQuery())
                ->select('`id_category`')
                ->from('category', 'c')
                ->where('c.`id_category` = '.(int) $idCategory)
        );

        return isset($row['id_category']);
    }

    /**
     * @param int $idGroup
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function setNewGroupForHome($idGroup)
    {
        if (!(int) $idGroup) {
            return false;
        }

        try {
            return Db::getInstance()->insert(
                'category_group',
                [
                    'id_category' => (int) Context::getContext()->shop->getCategory(),
                    'id_group'    => (int) $idGroup,
                ]
            );
        } catch (PrestaShopDatabaseException $e) {
            return false;
        }
    }

    /**
     * @param int $idCategory
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getUrlRewriteInformations($idCategory)
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('l.`id_lang`, c.`link_rewrite`')
                ->from('category_lang', 'c')
                ->leftJoin('lang', 'l', 'c.`id_lang` = l.`id_lang`')
                ->where('c.`id_category` = '.(int) $idCategory)
                ->where('l.`active` = 1')
                ->addCurrentShopRestriction('c')
        );
    }

    /**
     * @param int $idCategory
     * @param Shop|null $shop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function inShopStatic($idCategory, ?Shop $shop = null)
    {
        if (!$shop || !is_object($shop)) {
            $shop = Context::getContext()->shop;
        }

        if (!$interval = Category::getInterval($shop->getCategory())) {
            return false;
        }

        $row = Db::readOnly()->getRow(
            (new DbQuery())
                ->select('`nleft`, `nright`')
                ->from('category')
                ->where('`id_category` = '.(int) $idCategory)
        );

        if (! $row) {
            return false;
        }

        return (
            $row['nleft'] >= $interval['nleft'] &&
            $row['nright'] <= $interval['nright']
        );
    }

    /**
     * Return nleft and nright fields for a given category
     *
     * @param int $id
     *
     * @return array | false
     *
     * @throws PrestaShopException
     */
    public static function getInterval($id)
    {
        $cacheId = 'Category::getInterval_'.(int) $id;
        if (!Cache::isStored($cacheId)) {
            $result = Db::readOnly()->getRow(
                (new DbQuery())
                    ->select('`nleft`, `nright`, `level_depth`')
                    ->from('category')
                    ->where('`id_category` = '.(int) $id)
            );
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * @param array $idsCategory
     * @param int $idLang
     *
     * @return array|false
     *
     * @throws PrestaShopException
     */
    public static function getCategoryInformations($idsCategory, $idLang = null)
    {
        if ($idLang === null) {
            $idLang = Context::getContext()->language->id;
        }

        if (!is_array($idsCategory) || !count($idsCategory)) {
            return false;
        }

        $categories = [];
        $results = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('c.`id_category`, cl.`name`, cl.`link_rewrite`, cl.`id_lang`')
                ->from('category', 'c')
                ->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category` '.Shop::addSqlRestrictionOnLang('cl'))
                ->where('cl.`id_lang` = '.(int) $idLang)
                ->where('c.`id_category` IN ('.implode(',', array_map('intval', $idsCategory)).')')
        );

        foreach ($results as $category) {
            $categories[$category['id_category']] = $category;
        }

        return $categories;
    }

    /**
     * @param int|null $idLang
     * @param bool $active
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getRootCategories($idLang = null, $active = true)
    {
        if (!$idLang) {
            $idLang = Context::getContext()->language->id;
        }

        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('DISTINCT(c.`id_category`), cl.`name`')
                ->from('category', 'c')
                ->leftJoin('category_lang', 'cl', 'cl.`id_category` = c.`id_category` AND cl.`id_lang`='.(int) $idLang)
                ->where('`is_root_category` = 1 ' . static::getActiveColumnCondition($active, false))
        );
    }

    /**
     * @param int $idCategory
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getShopsByCategory($idCategory)
    {
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_shop`')
                ->from('category_shop')
                ->where('`id_category` = '.(int) $idCategory)
        );
    }

    /**
     * Update categories for a shop
     *
     * @param array $categories Categories list to associate a shop
     * @param int $idShop Categories list to associate a shop
     *
     * @return bool|false Update/insertion result
     *
     * @throws PrestaShopException
     */
    public static function updateFromShop($categories, $idShop)
    {
        $shop = new Shop($idShop);
        // if array is empty or if the default category is not selected, return false
        if (!is_array($categories) || !count($categories) || !in_array($shop->id_category, $categories)) {
            return false;
        }

        // delete categories for this shop
        Category::deleteCategoriesFromShop($idShop);

        // and add $categories to this shop
        return Category::addToShop($categories, $idShop);
    }

    /**
     * Delete every categories
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function deleteCategoriesFromShop($idShop)
    {
        return Db::getInstance()->delete('category_shop', 'id_shop = '.(int) $idShop);
    }

    /**
     * Add some categories to a shop
     *
     * @param array $categories
     * @param int $idShop
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function addToShop(array $categories, $idShop)
    {
        $sql = 'INSERT INTO `'._DB_PREFIX_.'category_shop` (`id_category`, `id_shop`) VALUES';
        $tabCategories = [];
        foreach ($categories as $idCategory) {
            $tabCategories[] = new Category($idCategory);
            $sql .= '("'.(int) $idCategory.'", "'.(int) $idShop.'"),';
        }
        // removing last comma to avoid SQL error
        $sql = substr($sql, 0, strlen($sql) - 1);

        $return = Db::getInstance()->execute($sql);
        // we have to update position for every new entries
        foreach ($tabCategories as $category) {
            /** @var Category $category */
            $category->addPosition(Category::getLastPosition($category->id_parent, $idShop), $idShop);
        }

        return $return;
    }

    /**
     * @param int $position
     * @param int|null $idShop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function addPosition($position, $idShop = null)
    {
        $return = true;
        $conn = Db::getInstance();
        if (is_null($idShop)) {
            if (Shop::getContext() != Shop::CONTEXT_SHOP) {
                foreach (Shop::getContextListShopID() as $idShop) {
                    $return = $conn->execute(
                        '
						INSERT INTO `'._DB_PREFIX_.'category_shop` (`id_category`, `id_shop`, `position`) VALUES
						('.(int) $this->id.', '.(int) $idShop.', '.(int) $position.')
						ON DUPLICATE KEY UPDATE `position` = '.(int) $position
                    ) && $return;
                }
            } else {
                $id = Context::getContext()->shop->id;
                $idShop = $id ? $id : Configuration::get('PS_SHOP_DEFAULT');
                $return = $conn->execute(
                    '
					INSERT INTO `'._DB_PREFIX_.'category_shop` (`id_category`, `id_shop`, `position`) VALUES
					('.(int) $this->id.', '.(int) $idShop.', '.(int) $position.')
					ON DUPLICATE KEY UPDATE `position` = '.(int) $position
                );
            }
        } else {
            $return = $conn->execute(
                '
			INSERT INTO `'._DB_PREFIX_.'category_shop` (`id_category`, `id_shop`, `position`) VALUES
			('.(int) $this->id.', '.(int) $idShop.', '.(int) $position.')
			ON DUPLICATE KEY UPDATE `position` = '.(int) $position
            );
        }

        return $return;
    }

    /** this function return the number of category + 1 having $id_category_parent as parent.
     *
     * @todo    rename that function to make it understandable (getNewLastPosition for example)
     *
     * @param int $idCategoryParent the parent category
     * @param int $idShop
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getLastPosition($idCategoryParent, $idShop)
    {
        if ((int) Db::readOnly()->getValue(
                '
				SELECT COUNT(c.`id_category`)
				FROM `'._DB_PREFIX_.'category` c
				LEFT JOIN `'._DB_PREFIX_.'category_shop` cs
				ON (c.`id_category` = cs.`id_category` AND cs.`id_shop` = '.(int) $idShop.')
				WHERE c.`id_parent` = '.(int) $idCategoryParent
            ) === 1
        ) {
            return 0;
        } else {
            return (1 + (int) Db::readOnly()->getValue(
                    '
				SELECT MAX(cs.`position`)
				FROM `'._DB_PREFIX_.'category` c
				LEFT JOIN `'._DB_PREFIX_.'category_shop` cs
				ON (c.`id_category` = cs.`id_category` AND cs.`id_shop` = '.(int) $idShop.')
				WHERE c.`id_parent` = '.(int) $idCategoryParent
                ));
        }
    }

    /**
     * @param bool $autoDate
     * @param bool $nullValues
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function add($autoDate = true, $nullValues = false)
    {
        if (!isset($this->level_depth)) {
            $this->level_depth = $this->calcLevelDepth();
        }

        if ($this->is_root_category && ($idRootCategory = (int) Configuration::get('PS_ROOT_CATEGORY'))) {
            $this->id_parent = $idRootCategory;
        }

        $ret = parent::add($autoDate, $nullValues);

        if (Tools::isSubmit('checkBoxShopAsso_category')) {
            foreach (Tools::getArrayValue('checkBoxShopAsso_category') as $idShop => $value) {
                $position = (int) Category::getLastPosition((int) $this->id_parent, $idShop);
                $this->addPosition($position, $idShop);
            }
        } else {
            foreach (Shop::getShops(true) as $shop) {
                $position = (int) Category::getLastPosition((int) $this->id_parent, $shop['id_shop']);
                $this->addPosition($position, $shop['id_shop']);
            }
        }
        if (!isset($this->doNotRegenerateNTree) || !$this->doNotRegenerateNTree) {
            Category::regenerateEntireNtree();
        }

        // Update group selection, if provided
        if (is_array($this->groupBox)) {
            $this->updateGroup($this->groupBox);
        }

        Hook::triggerEvent('actionCategoryAdd', ['category' => $this]);

        return $ret;
    }

    /**
     * Get the depth level for the category
     *
     * @return int Depth level
     *
     * @throws PrestaShopException
     */
    public function calcLevelDepth()
    {
        /* Root category */
        if (!$this->id_parent) {
            return 0;
        }

        $parentCategory = new Category((int) $this->id_parent);
        if (!Validate::isLoadedObject($parentCategory)) {
            throw new PrestaShopException('Parent category does not exist');
        }

        return $parentCategory->level_depth + 1;
    }

    /**
     * Re-calculate the values of all branches of the nested tree
     *
     * @throws PrestaShopException
     */
    public static function regenerateEntireNtree()
    {
        $id = Context::getContext()->shop->id;
        $idShop = $id ? $id : Configuration::get('PS_SHOP_DEFAULT');
        $categories = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('c.`id_category`, c.`id_parent`')
                ->from('category', 'c')
                ->leftJoin('category_shop', 'cs', 'c.`id_category` = cs.`id_category` AND cs.`id_shop` = '.(int) $idShop)
                ->orderBy('c.`id_parent`, cs.`position` ASC')
        );
        $categoriesArray = [];
        foreach ($categories as $category) {
            $categoriesArray[$category['id_parent']]['subcategories'][] = $category['id_category'];
        }
        $n = 1;

        if (isset($categoriesArray[0]) && $categoriesArray[0]['subcategories']) {
            Category::_subTree($categoriesArray, $categoriesArray[0]['subcategories'][0], $n);
        }
    }

    /**
     * @param array $categories
     * @param int $idCategory
     * @param int $n
     *
     * @deprecated 1.0.0
     * @throws PrestaShopException
     */
    protected static function _subTree(&$categories, $idCategory, &$n)
    {
        return static::subTree($categories, $idCategory, $n);
    }

    /**
     * @param array $categories
     * @param int $idCategory
     * @param int $n
     *
     * @throws PrestaShopException
     */
    protected static function subTree(&$categories, $idCategory, &$n)
    {
        $left = $n++;
        if (isset($categories[(int) $idCategory]['subcategories'])) {
            foreach ($categories[(int) $idCategory]['subcategories'] as $idSubcategory) {
                Category::_subTree($categories, (int) $idSubcategory, $n);
            }
        }
        $right = (int) $n++;

        Db::getInstance()->execute(
            '
		UPDATE '._DB_PREFIX_.'category
		SET nleft = '.(int) $left.', nright = '.(int) $right.'
		WHERE id_category = '.(int) $idCategory.' LIMIT 1'
        );
    }

    /**
     * Update customer groups associated to the object
     *
     * @param int[] $groupIds List of group IDs
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function updateGroup($groupIds)
    {
        $this->cleanGroups();
        if (is_array($groupIds)) {
            $this->addGroups($groupIds);
        }
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function cleanGroups()
    {
        $categoryId = (int)$this->id;
        Cache::clean('Category::getGroups_' . $categoryId);
        return Db::getInstance()->delete('category_group', 'id_category = '.$categoryId);
    }

    /**
     * @param array $groups
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function addGroups($groups)
    {
        $categoryId = (int)$this->id;
        $result = true;
        foreach ($groups as $groupId) {
            $groupId = (int)$groupId;
            if ($groupId) {
                $result = Db::getInstance()->insert('category_group', [
                    'id_category' => $categoryId,
                    'id_group' => $groupId,
                ]) && $result;
            }
        }
        Cache::clean('Category::getGroups_' . $categoryId);
        return $result;
    }

    /**
     * update category positions in parent
     *
     * @param bool $nullValues
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function update($nullValues = false)
    {
        if ($this->id_parent == $this->id) {
            throw new PrestaShopException('a category cannot be its own parent');
        }

        if (PageCache::isEnabled()) {
            PageCache::invalidateEntity('category', $this->id);
        }

        if ($this->is_root_category && $this->id_parent != (int) Configuration::get('PS_ROOT_CATEGORY')) {
            $this->is_root_category = 0;
        }

        // Update group selection, if provided
        if (is_array($this->groupBox)) {
            $this->updateGroup($this->groupBox);
        }

        if ($this->level_depth != $this->calcLevelDepth()) {
            $this->level_depth = $this->calcLevelDepth();
            $changed = true;
        }

        // If the parent category was changed, we don't want to have 2 categories with the same position
        if (!isset($changed)) {
            $changed = $this->getDuplicatePosition();
        }
        if ($changed) {
            if (Tools::isSubmit('checkBoxShopAsso_category')) {
                foreach (Tools::getArrayValue('checkBoxShopAsso_category') as $idShop => $value) {
                    $this->addPosition((int) Category::getLastPosition((int) $this->id_parent, (int) $idShop), (int) $idShop);
                }
            } else {
                foreach (Shop::getShops(true) as $shop) {
                    $this->addPosition((int) Category::getLastPosition((int) $this->id_parent, $shop['id_shop']), $shop['id_shop']);
                }
            }
        }

        $ret = parent::update($nullValues);
        if ($changed && (!isset($this->doNotRegenerateNTree) || !$this->doNotRegenerateNTree)) {
            $this->cleanPositions((int) $this->id_parent);
            Category::regenerateEntireNtree();
            $this->recalculateLevelDepth($this->id);
        }

        Hook::triggerEvent('actionCategoryUpdate', ['category' => $this]);

        return $ret;
    }

    /**
     * Search for another category with the same parent and the same position
     *
     * @return false|null|string first category found
     *
     * @throws PrestaShopException
     */
    public function getDuplicatePosition()
    {
        return Db::readOnly()->getValue(
            '
		SELECT c.`id_category`
		FROM `'._DB_PREFIX_.'category` c
		'.Shop::addSqlAssociation('category', 'c').'
		WHERE c.`id_parent` = '.(int) $this->id_parent.'
		AND category_shop.`position` = '.(int) $this->position.'
		AND c.`id_category` != '.(int) $this->id
        );
    }

    /**
     * cleanPositions keep order of category in $id_category_parent,
     * but remove duplicate position. Should not be used if positions
     * are clean at the beginning !
     *
     * @param int|null $idCategoryParent
     *
     * @return bool true if succeed
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function cleanPositions($idCategoryParent = null)
    {
        if ($idCategoryParent === null) {
            return false;
        }

        $return = true;
        $result = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('c.`id_category`')
                ->from('category', 'c')
                ->join(Shop::addSqlAssociation('category', 'c'))
                ->where('c.`id_parent` = '.(int) $idCategoryParent)
                ->orderBy('category_shop.`position`')
        );
        $count = count($result);
        for ($i = 0; $i < $count; $i++) {
            $return = Db::getInstance()->execute(
                '
            UPDATE `'._DB_PREFIX_.'category` c '.Shop::addSqlAssociation('category', 'c').'
            SET c.`position` = '.(int) ($i).',
            category_shop.`position` = '.(int) ($i).',
            c.`date_upd` = "'.date('Y-m-d H:i:s').'"
            WHERE c.`id_parent` = '.(int) $idCategoryParent.' AND c.`id_category` = '.(int) $result[$i]['id_category']
            ) && $return;
        }

        return $return;
    }

    /**
     * Updates level_depth for all children of the given id_category
     *
     * @param int $idCategory parent category
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function recalculateLevelDepth($idCategory)
    {
        if (!is_numeric($idCategory)) {
            throw new PrestaShopException('id category is not numeric');
        }
        /* Gets all children */
        $conn = Db::getInstance();
        $categories = $conn->getArray(
            (new DbQuery())
                ->select('`id_category`, `id_parent`, `level_depth`')
                ->from('category')
                ->where('`id_parent` = '.(int) $idCategory)
        );
        /* Gets level_depth */
        $level = $conn->getRow(
            (new DbQuery())
                ->select('level_depth')
                ->from('category')
                ->where('`id_category` = '.(int) $idCategory)
        );
        /* Updates level_depth for all children */
        foreach ($categories as $subCategory) {
            $conn->execute(
                '
				UPDATE '._DB_PREFIX_.'category
				SET level_depth = '.(int) ($level['level_depth'] + 1).'
				WHERE id_category = '.(int) $subCategory['id_category']
            );
            /* Recursive call */
            $this->recalculateLevelDepth($subCategory['id_category']);
        }
    }

    /**
     * @throws PrestaShopException
     */
    public function toggleStatus()
    {
        $result = parent::toggleStatus();
        Hook::triggerEvent('actionCategoryUpdate', ['category' => $this]);

        return $result;
    }

    /**
     * Recursive scan of subcategories
     *
     * @param int $maxDepth Maximum depth of the tree (i.e. 2 => 3 levels depth)
     * @param int $currentDepth specify the current depth in the tree (don't use it, only for rucursivity!)
     * @param int $idLang Specify the id of the language used
     * @param array $excludedIdsArray specify a list of ids to exclude of results
     *
     * @return array Subcategories lite tree
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function recurseLiteCategTree($maxDepth = 3, $currentDepth = 0, $idLang = null, $excludedIdsArray = null)
    {
        $idLang = is_null($idLang) ? Context::getContext()->language->id : (int) $idLang;

        $children = [];
        $subcats = $this->getSubCategories($idLang, true);
        if (($maxDepth == 0 || $currentDepth < $maxDepth) && $subcats && count($subcats)) {
            foreach ($subcats as &$subcat) {
                if (!$subcat['id_category']) {
                    break;
                } elseif (!is_array($excludedIdsArray) || !in_array($subcat['id_category'], $excludedIdsArray)) {
                    $categ = new Category($subcat['id_category'], $idLang);
                    $children[] = $categ->recurseLiteCategTree($maxDepth, $currentDepth + 1, $idLang, $excludedIdsArray);
                }
            }
        }

        if (is_array($this->description)) {
            foreach ($this->description as $lang => $description) {
                $this->description[$lang] = Category::getDescriptionClean($description);
            }
        } else {
            $this->description = Category::getDescriptionClean($this->description);
        }

        return [
            'id'       => (int) $this->id,
            'link'     => Context::getContext()->link->getCategoryLink($this->id, $this->link_rewrite),
            'name'     => $this->name,
            'desc'     => $this->description,
            'children' => $children,
        ];
    }

    /**
     * Return current category childs
     *
     * @param int $idLang Language ID
     * @param bool $active return only active categories
     *
     * @return array Categories
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getSubCategories($idLang, $active = true)
    {
        $sqlGroupsWhere = '';
        $sqlGroupsJoin = '';
        if (Group::isFeatureActive()) {
            $sqlGroupsJoin = 'LEFT JOIN `'._DB_PREFIX_.'category_group` cg ON (cg.`id_category` = c.`id_category`)';
            $groups = FrontController::getCurrentCustomerGroups();
            $sqlGroupsWhere = 'AND cg.`id_group` '.(count($groups) ? 'IN ('.implode(',', $groups).')' : '='.(int) Group::getCurrent()->id);
        }

        $result = Db::readOnly()->getArray(
            '
		SELECT c.*, cl.id_lang, cl.name, cl.description, cl.link_rewrite, cl.meta_title, cl.meta_keywords, cl.meta_description
		FROM `'._DB_PREFIX_.'category` c
		'.Shop::addSqlAssociation('category', 'c').'
		LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.`id_category` = cl.`id_category` AND `id_lang` = '.(int) $idLang.' '.Shop::addSqlRestrictionOnLang('cl').')
		'.$sqlGroupsJoin.'
		WHERE `id_parent` = '.(int) $this->id.'
		'.static::getActiveColumnCondition($active, true).'
		'.$sqlGroupsWhere.'
		GROUP BY c.`id_category`
		ORDER BY `level_depth` ASC, category_shop.`position` ASC'
        );

        foreach ($result as &$row) {
            $row['id_image'] = $row['id_category'];
            $row['legend'] = 'no picture';
        }

        return $result;
    }

    /**
     * @param string $description
     *
     * @return string
     */
    public static function getDescriptionClean($description)
    {
        return Tools::getDescriptionClean($description);
    }

    /**
     * Delete several categories from database
     *
     * return boolean Deletion result
     *
     * @throws PrestaShopException
     */
    public function deleteSelection($categories)
    {
        $return = true;
        foreach ($categories as $idCategory) {
            $category = new Category($idCategory);
            if ($category->isRootCategoryForAShop()) {
                $return = false;
            } else {
                $return = $category->delete() && $return;
            }
        }

        return $return;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function isRootCategoryForAShop()
    {
        return (bool) Db::readOnly()->getValue(
            (new DbQuery())
                ->select('`id_shop`')
                ->from('shop')
                ->where('`id_category` = '.(int) $this->id)
        );
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function delete()
    {
        if ((int) $this->id === 0 || (int) $this->id === (int) Configuration::get('PS_ROOT_CATEGORY')) {
            return false;
        }

        if (PageCache::isEnabled()) {
            PageCache::invalidateEntity('category', $this->id);
        }

        $this->clearCache();

        $deletedChildren = $allCat = $this->getAllChildren();
        $allCat[] = $this;
        foreach ($allCat as $cat) {
            /** @var Category $cat */
            $cat->deleteLite();
            if (!$this->hasMultishopEntries()) {
                $cat->deleteImage();
                $cat->cleanGroups();
                $cat->cleanAssoProducts();
                // Delete associated restrictions on cart rules
                CartRule::cleanProductRuleIntegrity('categories', [$cat->id]);
                Category::cleanPositions($cat->id_parent);
                /* Delete Categories in GroupReduction */
                if (GroupReduction::getGroupsReductionByCategoryId((int) $cat->id)) {
                    GroupReduction::deleteCategory($cat->id);
                }
            }
        }

        /* Rebuild the nested tree */
        if (!$this->hasMultishopEntries() && (!isset($this->doNotRegenerateNTree) || !$this->doNotRegenerateNTree)) {
            Category::regenerateEntireNtree();
        }

        Hook::triggerEvent('actionCategoryDelete', ['category' => $this, 'deleted_children' => $deletedChildren]);

        return true;
    }

    /**
     * Return an array of all children of the current category
     *
     * @param int $idLang
     *
     * @return PrestaShopCollection Collection of Category
     *
     * @throws PrestaShopException
     */
    public function getAllChildren($idLang = null)
    {
        if (is_null($idLang)) {
            $idLang = Context::getContext()->language->id;
        }

        $categories = new PrestaShopCollection('Category', $idLang);
        $categories->where('nleft', '>', (int)$this->nleft);
        $categories->where('nright', '<', (int)$this->nright);

        return $categories;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function deleteLite()
    {
        // Directly call the parent of delete, in order to avoid recursion
        return parent::delete();
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function cleanAssoProducts()
    {
        return Db::getInstance()->delete('category_product', 'id_category = '.(int) $this->id);
    }

    /**
     * @return int
     */
    public function getShopID()
    {
        return $this->id_shop;
    }

    /**
     * Returns category products
     *
     * @param int|null $idLang Language ID
     * @param int|null $p Page number
     * @param int|null $n Number of products per page
     * @param string|null $orderBy ORDER BY column
     * @param string|null $orderWay Order way
     * @param bool $getTotal If set to true, returns the total number of results only
     * @param bool $active If set to true, finds only active products
     * @param bool $random If true, sets a random filter for returned products
     * @param int $randomNumberProducts Number of products to return if random is activated
     * @param bool $checkAccess If set tot rue, check if the current customer
     *                                             can see products from this category
     * @param Context|null $context
     *
     * @return array|int|false Products, number of products or false (no access)
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function getProducts($idLang, $p, $n, $orderBy = null, $orderWay = null, $getTotal = false, $active = true, $random = false, $randomNumberProducts = 1, $checkAccess = true, ?Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        if ($checkAccess && !$this->checkAccess($context->customer->id)) {
            return false;
        }

        $front = in_array($context->controller->controller_type, ['front', 'modulefront']);
        $idSupplier = Tools::getIntValue('id_supplier');

        $subcats = $this->getAllSubcategories();
        $catsToSearchIn = [$this->id];
        if($subcats && $this->display_from_sub) {
            foreach ($subcats as $scat) {
                $catsToSearchIn[] = $scat['id_category'];
            }
        }

        /** Return only the number of products */
        if ($getTotal) {
            $sql = 'SELECT COUNT(DISTINCT(cp.`id_product`)) AS total
					FROM `'._DB_PREFIX_.'product` p
					'.Shop::addSqlAssociation('product', 'p').'
					LEFT JOIN `'._DB_PREFIX_.'category_product` cp ON p.`id_product` = cp.`id_product`
					WHERE cp.`id_category` IN ('.implode(',', $catsToSearchIn).')'.
                ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '').
                ($active ? ' AND product_shop.`active` = 1' : '').
                ($idSupplier ? ' AND p.id_supplier = '.(int) $idSupplier : '');

            return (int) Db::readOnly()->getValue($sql);
        }

        if ($p < 1) {
            $p = 1;
        }

        /** Tools::strtolower is a fix for all modules which are now using lowercase values for 'orderBy' parameter */
        $orderBy = Validate::isOrderBy($orderBy) ? mb_strtolower($orderBy) : 'position';
        $orderWay = Validate::isOrderWay($orderWay) ? mb_strtoupper($orderWay) : 'ASC';

        $orderByPrefix = false;
        if ($orderBy == 'id_product' || $orderBy == 'date_add' || $orderBy == 'date_upd') {
            $orderByPrefix = 'p';
        } elseif ($orderBy == 'name') {
            $orderByPrefix = 'pl';
        } elseif ($orderBy == 'manufacturer' || $orderBy == 'manufacturer_name') {
            $orderByPrefix = 'm';
            $orderBy = 'name';
        } elseif ($orderBy == 'position') {
            $orderByPrefix = 'cp';
        }

        if ($orderBy == 'price') {
            $orderBy = 'orderprice';
        }

        $nbDaysNewProduct = Configuration::get('PS_NB_DAYS_NEW_PRODUCT');
        if (!Validate::isUnsignedInt($nbDaysNewProduct)) {
            $nbDaysNewProduct = 20;
        }

        $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) AS quantity'.(Combination::isFeatureActive() ? ', IFNULL(product_attribute_shop.id_product_attribute, 0) AS id_product_attribute,
					product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity' : '').', pl.`description`, pl.`description_short`, pl.`available_now`,
					pl.`available_later`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, image_shop.`id_image` id_image,
					il.`legend` as legend, m.`name` AS manufacturer_name, cl.`name` AS category_default,
					DATEDIFF(product_shop.`date_add`, DATE_SUB("'.date('Y-m-d').' 00:00:00",
					INTERVAL '.(int) $nbDaysNewProduct.' DAY)) > 0 AS new, product_shop.price AS orderprice
				FROM `'._DB_PREFIX_.'category_product` cp
				LEFT JOIN `'._DB_PREFIX_.'product` p
					ON p.`id_product` = cp.`id_product`
				'.Shop::addSqlAssociation('product', 'p').
            (Combination::isFeatureActive() ? ' LEFT JOIN `'._DB_PREFIX_.'product_attribute_shop` product_attribute_shop
				ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop='.(int) $context->shop->id.')' : '').'
				'.Product::sqlStock('p', 0).'
				LEFT JOIN `'._DB_PREFIX_.'category_lang` cl
					ON (product_shop.`id_category_default` = cl.`id_category`
					AND cl.`id_lang` = '.(int) $idLang.Shop::addSqlRestrictionOnLang('cl').')
				LEFT JOIN `'._DB_PREFIX_.'product_lang` pl
					ON (p.`id_product` = pl.`id_product`
					AND pl.`id_lang` = '.(int) $idLang.Shop::addSqlRestrictionOnLang('pl').')
				LEFT JOIN `'._DB_PREFIX_.'image_shop` image_shop
					ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop='.(int) $context->shop->id.')
				LEFT JOIN `'._DB_PREFIX_.'image_lang` il
					ON (image_shop.`id_image` = il.`id_image`
					AND il.`id_lang` = '.(int) $idLang.')
				LEFT JOIN `'._DB_PREFIX_.'manufacturer` m
					ON m.`id_manufacturer` = p.`id_manufacturer`
				WHERE product_shop.`id_shop` = '.(int) $context->shop->id.'
					AND cp.`id_category` IN ('.implode(',', $catsToSearchIn).')'
            .($active ? ' AND product_shop.`active` = 1' : '')
            .($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
            .($idSupplier ? ' AND p.id_supplier = '.(int) $idSupplier : '')
            .' GROUP BY cp.id_product';

        if ($random === true) {
            $sql .= ' ORDER BY RAND() LIMIT '.(int) $randomNumberProducts;
        } else {
            $sql .= ' ORDER BY '.(!empty($orderByPrefix) ? $orderByPrefix.'.' : '').'`'.bqSQL($orderBy).'` '.pSQL($orderWay).'
			LIMIT '.(((int) $p - 1) * (int) $n).','.(int) $n;
        }

        $result = Db::readOnly()->getArray($sql);

        if (!$result) {
            return [];
        }

        if ($orderBy == 'orderprice') {
            Tools::orderbyPrice($result, $orderWay);
        }

        /** Modify SQL result */
        return Product::getProductsProperties($idLang, $result);
    }

    /**
     * checkAccess return true if id_customer is in a group allowed to see this category.
     *
     * @param int|null $idCustomer
     *
     * @access  public
     * @return bool true if access allowed for customer $id_customer
     *
     * @throws PrestaShopException
     */
    public function checkAccess($idCustomer)
    {
        $cacheId = 'Category::checkAccess_'.(int) $this->id.'-'.$idCustomer.(!$idCustomer ? '-'.(int) Group::getCurrent()->id : '');
        if (!Cache::isStored($cacheId)) {
            $connection = Db::readOnly();
            if (!$idCustomer) {
                $result = (bool) $connection->getValue(
                    '
				SELECT ctg.`id_group`
				FROM '._DB_PREFIX_.'category_group ctg
				WHERE ctg.`id_category` = '.(int) $this->id.' AND ctg.`id_group` = '.(int) Group::getCurrent()->id
                );
            } else {
                $result = (bool) $connection->getValue(
                    '
				SELECT ctg.`id_group`
				FROM '._DB_PREFIX_.'category_group ctg
				INNER JOIN '._DB_PREFIX_.'customer_group cg ON (cg.`id_group` = ctg.`id_group` AND cg.`id_customer` = '.(int) $idCustomer.')
				WHERE ctg.`id_category` = '.(int) $this->id
                );
            }
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Return an array of all parents of the current category
     *
     * @param int $idLang
     *
     * @return PrestaShopCollection Collection of Category
     *
     * @throws PrestaShopException
     */
    public function getAllParents($idLang = null)
    {
        if (is_null($idLang)) {
            $idLang = Context::getContext()->language->id;
        }

        $categories = new PrestaShopCollection('Category', $idLang);
        $categories->where('nleft', '<', (int)$this->nleft);
        $categories->where('nright', '>', (int)$this->nright);
        $categories->orderBy('nleft');

        return $categories;
    }

    /**
     * Returns path to category.
     *
     * @param int $categoryId leaf category ID
     * @param int|null $idLang language context
     * @param bool $includeRoot if true, Root pseudo-category will be included in the result
     *
     * @return Category[]
     * @throws PrestaShopException
     */
    public static function getCategoryPath(int $categoryId, $idLang = null, $includeRoot = false)
    {
        if (is_null($idLang)) {
            $idLang = (int)Context::getContext()->language->id;
        }

        $path = [];
        $leaf = new Category($categoryId, $idLang);
        if (Validate::isLoadedObject($leaf)) {
            $parents = $leaf->getAllParents($idLang);

            /** @var Category $parent */
            foreach ($parents as $parent) {
                if ($includeRoot || $parent->id_parent) {
                    $path[] = $parent;
                }
            }
            $path[] = $leaf;
        }
        return $path;
    }

    /**
     * @param Link|null $link
     * @param int|null $idLang
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function getLink(?Link $link = null, $idLang = null)
    {
        if (!$link) {
            $link = Context::getContext()->link;
        }

        if (!$idLang && is_array($this->link_rewrite)) {
            $idLang = Context::getContext()->language->id;
        }

        return $link->getCategoryLink(
            $this,
            is_array($this->link_rewrite) ? $this->link_rewrite[$idLang] : $this->link_rewrite,
            $idLang
        );
    }

    /**
     * @param int|null $idLang
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function getName($idLang = null)
    {
        if (is_array($this->name)) {
            // object was loaded with all language context
            $nameArray = $this->name;
        } else {
            // object was loaded in single language context

            // if object was loaded for requested language, we can return name directly
            if ($idLang && $this->id_lang == $idLang) {
                return $this->name;
            }
            if (!$idLang && $this->id_lang == Context::getContext()->language->id) {
                return $this->name;
            }
            if (!$idLang && $this->id_lang == Configuration::get('PS_LANG_DEFAULT')) {
                return $this->name;
            }

            // object was loaded in different language context than requested, we need to load names from db
            $connection = Db::readOnly();
            $nameArray = [];
            $rows = $connection->getArray((new DbQuery())
                ->select('id_lang, name')
                ->from('category_lang')
                ->where('id_category = ' . (int)$this->id)
                ->where(Shop::getSqlRestriction())
            );
            foreach ($rows as $row) {
                $nameArray[(int)$row['id_lang']] = $row['name'];
            }
        }

        if (!$idLang) {
            if (isset($nameArray[Context::getContext()->language->id])) {
                $idLang = Context::getContext()->language->id;
            } else {
                $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
            }
        }

        return $nameArray[$idLang] ?? '';
    }

    /**
     * Get Each parent category of this category until the root category
     *
     * @param int $idLang Language ID
     *
     * @return array Corresponding categories
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getParentsCategories($idLang = null)
    {
        static $parentCategoryCache = [];

        $context = Context::getContext()->cloneContext();
        $context->shop = clone($context->shop);

        if (is_null($idLang)) {
            $idLang = $context->language->id;
        }

        $categories = null;
        $idCurrent = $this->id;
        if (count(Category::getCategoriesWithoutParent()) > 1 && Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE') && count(Shop::getShops(true, null, true)) != 1) {
            $context->shop->id_category = (int) Configuration::get('PS_ROOT_CATEGORY');
        } elseif (!$context->shop->id) {
            $context->shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
        }
        $idShop = $context->shop->id;

        if (!isset($parentCategoryCache[$idShop])) {
            $parentCategoryCache[$idShop] = [];
        }
        if (!isset($parentCategoryCache[$idShop][$idLang])) {
            $parentCategoryCache[$idShop][$idLang] = [];
        }

        while (true) {
            if (!empty($parentCategoryCache[$idShop][$idLang][$idCurrent])) {
                $result = $parentCategoryCache[$idShop][$idLang][$idCurrent];
            } else {
                $sql = (new DbQuery())
                    ->select('c.*, cl.*')
                    ->from('category', 'c')
                    ->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category`')
                    ->where('`id_lang` = '.(int) $idLang.Shop::addSqlRestrictionOnLang('cl'));
                if (Shop::isFeatureActive() && Shop::getContext() == Shop::CONTEXT_SHOP) {
                    $sql->leftJoin('category_shop', 'cs', 'c.`id_category` = cs.`id_category` AND cs.`id_shop` = '.(int) $idShop);
                }
                $sql->where('c.`id_category` = '.(int) $idCurrent);
                if (Shop::isFeatureActive() && Shop::getContext() == Shop::CONTEXT_SHOP) {
                    $sql->where('cs.`id_shop` = '.(int) $context->shop->id);
                }
                $rootCategory = Category::getRootCategory();
                if (Shop::isFeatureActive() && Shop::getContext() == Shop::CONTEXT_SHOP
                    && (!Tools::isSubmit('id_category') || Tools::getIntValue('id_category') === (int) $rootCategory->id || (int)$rootCategory->id === (int)$context->shop->id_category)
                ) {
                    $sql->where('c.`id_parent` != 0');
                }

                $result = Db::readOnly()->getRow($sql);
                $parentCategoryCache[$idShop][$idLang][$idCurrent] = $result;
            }

            if ($result) {
                $categories[] = $result;
            } elseif (!$categories) {
                $categories = [];
            }
            if (!$result || ($result['id_category'] == $context->shop->id_category)) {
                return $categories;
            }
            $idCurrent = $result['id_parent'];
        }
    }

    /**
     * @param int $idGroup
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function addGroupsIfNoExist($idGroup)
    {
        $idGroup = (int)$idGroup;
        $groups = $this->getGroups();
        if (! in_array($idGroup, $groups)) {
            return $this->addGroups([ $idGroup ]);
        }

        return false;
    }

    /**
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getGroups()
    {
        $categoryId = (int)$this->id;
        if ($categoryId) {
            $cache_id = 'Category::getGroups_' . $categoryId;
            if (!Cache::isStored($cache_id)) {
                $result = Db::readOnly()->getArray(
                    (new DbQuery())
                        ->select('cg.`id_group`')
                        ->from('category_group', 'cg')
                        ->where('cg.`id_category` = ' . $categoryId)
                );
                $groups = [];
                foreach ($result as $group) {
                    $groups[] = (int)$group['id_group'];
                }
                Cache::store($cache_id, $groups);

                return $groups;
            }

            return Cache::retrieve($cache_id);
        }
        return [];
    }

    /**
     * @param int $way
     * @param int $position
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function updatePosition($way, $position)
    {
        if (!$res = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('cp.`id_category`, category_shop.`position`, cp.`id_parent`')
                ->from('category', 'cp')
                ->join(Shop::addSqlAssociation('category', 'cp'))
                ->where('cp.`id_parent` = '.(int) $this->id_parent)
                ->orderBy('category_shop.`position` ASC')
        )) {
            return false;
        }

        $moved_category = false;
        foreach ($res as $category) {
            if ((int) $category['id_category'] == (int) $this->id) {
                $moved_category = $category;
            }
        }

        if ($moved_category === false) {
            return false;
        }
        // < and > statements rather than BETWEEN operator
        // since BETWEEN is treated differently according to databases
        $conn = Db::getInstance();
        $result = ($conn->execute(
                '
            UPDATE `'._DB_PREFIX_.'category` c '.Shop::addSqlAssociation('category', 'c').'
            SET c.`position`= c.`position` '.($way ? '- 1' : '+ 1').',
            category_shop.`position`= category_shop.`position` '.($way ? '- 1' : '+ 1').',
            c.`date_upd` = "'.date('Y-m-d H:i:s').'"
            WHERE category_shop.`position`
            '.($way
                    ? '> '.(int) $moved_category['position'].' AND category_shop.`position` <= '.(int) $position
                    : '< '.(int) $moved_category['position'].' AND category_shop.`position` >= '.(int) $position).'
            AND c.`id_parent`='.(int) $moved_category['id_parent']
            )
            && $conn->execute(
                '
            UPDATE `'._DB_PREFIX_.'category` c '.Shop::addSqlAssociation('category', 'c').'
            SET c.`position` = '.(int) $position.',
            category_shop.`position` = '.(int) $position.',
            c.`date_upd` = "'.date('Y-m-d H:i:s').'"
            WHERE c.`id_parent` = '.(int) $moved_category['id_parent'].'
            AND c.`id_category`='.(int) $moved_category['id_category']
            ));
        Hook::triggerEvent('actionCategoryUpdate', ['category' => new Category($moved_category['id_category'])]);

        return $result;
    }

    /**
     * Check if current category is a child of shop root category
     *
     * @param Shop|null $shop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function inShop(?Shop $shop = null)
    {
        if (! Validate::isLoadedObject($this)) {
            return false;
        }

        if (!$shop) {
            $shop = Context::getContext()->shop;
        }

        if (!$interval = Category::getInterval($shop->getCategory())) {
            return false;
        }

        return ($this->nleft >= $interval['nleft'] && $this->nright <= $interval['nright']);
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getChildrenWs()
    {
        $result = Db::readOnly()->getArray(
            '
		SELECT c.`id_category` AS id
		FROM `'._DB_PREFIX_.'category` c
		'.Shop::addSqlAssociation('category', 'c').'
		WHERE c.`id_parent` = '.(int) $this->id.'
		AND category_shop.`active` = 1
		ORDER BY category_shop.`position` ASC'
        );

        return $result;
    }

    /**
     * Returns products associated with this category
     *
     * @return int[]
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getAssociatedProducts()
    {
        $connection = Db::readOnly();
        $result = $connection->getArray((new DbQuery())
            ->select('id_product')
            ->from('category_product')
            ->where('id_category = ' . (int) $this->id)
            ->orderBy('`position` ASC, `id_product`')
        );
        return array_map('intval', array_column($result, 'id_product'));
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getProductsWs()
    {
        $result = Db::readOnly()->getArray(
            '
		SELECT cp.`id_product` AS id
		FROM `'._DB_PREFIX_.'category_product` cp
		WHERE cp.`id_category` = '.(int) $this->id.'
		ORDER BY `position` ASC'
        );

        return $result;
    }

    /**
     * @return int
     *
     * @throws PrestaShopException
     */
    public function getWsNbProductsRecursive()
    {
        if (! Validate::isLoadedObject($this)) {
            return -1;
        }
        $nb_product_recursive = (int)Db::readOnly()->getValue(
            '
			SELECT COUNT(DISTINCT(id_product))
			FROM  `'._DB_PREFIX_.'category_product`
			WHERE id_category = '.(int) $this->id.' OR
			EXISTS (
				SELECT 1
				FROM `'._DB_PREFIX_.'category` c2
				'.Shop::addSqlAssociation('category', 'c2').'
				WHERE `'._DB_PREFIX_.'category_product`.id_category = c2.id_category
					AND c2.nleft > '.(int) $this->nleft.'
					AND c2.nright < '.(int) $this->nright.'
					AND category_shop.active = 1
			)
		'
        );
        if (!$nb_product_recursive) {
            return -1;
        }

        return $nb_product_recursive;
    }

    /**
     * @param int $idShop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function isParentCategoryAvailable($idShop)
    {
        $id = Context::getContext()->shop->id;
        $idShop = $id ? $id : Configuration::get('PS_SHOP_DEFAULT');

        return (bool) Db::readOnly()->getValue(
            (new DbQuery())
                ->select('c.`id_category`')
                ->from('category', 'c')
                ->join(Shop::addSqlAssociation('category', 'c', true, null, true))
                ->where('category_shop.`id_shop` = '.(int) $idShop)
                ->where('c.`id_parent` = '.(int) $this->id_parent)
        );
    }

    /**
     * Add association between shop and categories
     *
     * @param int $idShop
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function addShop($idShop)
    {
        $data = [];
        if (!$idShop) {
            foreach (Shop::getShops(false) as $shop) {
                if (!$this->existsInShop($shop['id_shop'])) {
                    $data[] = [
                        'id_category' => (int) $this->id,
                        'id_shop'     => (int) $shop['id_shop'],
                    ];
                }
            }
        } elseif (!$this->existsInShop($idShop)) {
            $data[] = [
                'id_category' => (int) $this->id,
                'id_shop'     => (int) $idShop,
            ];
        }

        return Db::getInstance()->insert('category_shop', $data);
    }

    /**
     * @param int $id_shop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function existsInShop($id_shop)
    {
        return (bool) Db::readOnly()->getValue(
            (new DbQuery())
                ->select('`id_category`')
                ->from('category_shop')
                ->where('`id_category` = '.(int) $this->id)
                ->where('`id_shop` = '.(int) $id_shop)
        );
    }

    /**
     * Delete category from shop $id_shop
     *
     * @param int $idShop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function deleteFromShop($idShop)
    {
        return Db::getInstance()->delete(
            'category_shop',
            '`id_shop` = '.(int) $idShop.' AND id_category = '.(int) $this->id
        );
    }

    /**
     * Recursively add specified category childs to $to_delete array
     *
     * @param array &$toDelete Array reference where categories ID will be saved
     * @param int $idCategory Parent category ID
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @deprecated 1.4.0 -- not used by core
     */
    protected function recursiveDelete(&$toDelete, $idCategory)
    {
        Tools::displayAsDeprecated();
        if (PageCache::isEnabled()) {
            PageCache::invalidateEntity('category', $this->id);
        }

        $result = Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_category`')
                ->from('category')
                ->where('`id_parent` = '.(int) $idCategory)
        );
        foreach ($result as $row) {
            $toDelete[] = (int) $row['id_category'];
            $this->recursiveDelete($toDelete, (int) $row['id_category']);
        }
    }

    /**
     * Get all ids of all subcategories of the current category
     *
     * @return array list of ids of the subcategories
     *
     * @throws PrestaShopException
     */
    public function getAllSubcategories()
    {
        if (! Validate::isLoadedObject($this)) {
            return [];
        }
        return Db::readOnly()->getArray(
            (new DbQuery())
                ->select('`id_category`')
                ->from('category')
                ->where('`nleft` > '.(int)$this->nleft.' AND `nright` < '.(int)$this->nright)
        );
    }

    /**
     * @param TableSchema $table
     */
    public static function processTableSchema($table)
    {
        if ($table->getNameWithoutPrefix() === 'category_lang') {
            $table->reorderColumns(['id_category', 'id_shop', 'id_lang']);
        }
    }

    /**
     * Database initialization callback
     *
     * @param Db $conn
     * @return void
     * @throws PrestaShopException
     */
    public static function initializationCallback(Db $conn)
    {
        // in 1.4.0 columns 'active', 'display_from_sub', 'date_add', and 'date_upd' were moved to
        // shop table. We need to initialize them properly
        $conn->execute('
            UPDATE ' . _DB_PREFIX_ . 'category_shop cs
            INNER JOIN '. _DB_PREFIX_ .'category c ON (cs.id_category = c.id_category)
            SET cs.active = c.active,
                cs.display_from_sub = c.display_from_sub,
                cs.date_add = c.date_add,
                cs.date_upd = c.date_upd
            WHERE IFNULL(cs.date_add, \'1970-01-01\') < \'1971-01-01\'
        ');

        ImageEntity::rebuildImageEntities(static::class, self::$definition['images']);
    }
}
