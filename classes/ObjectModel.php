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
 *  @author    thirty bees <contact@thirtybees.com>
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2017-2024 thirty bees
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

use Thirtybees\Core\Error\ErrorUtils;

/**
 * Class ObjectModelCore
 */
abstract class ObjectModelCore implements Core_Foundation_Database_EntityInterface
{
    /**
     * List of field types
     */
    const TYPE_INT     = 1;
    const TYPE_BOOL    = 2;
    const TYPE_STRING  = 3;
    const TYPE_FLOAT   = 4;
    const TYPE_DATE    = 5;
    const TYPE_HTML    = 6;
    const TYPE_NOTHING = 7;
    const TYPE_SQL     = 8;
    const TYPE_PRICE   = 9;

    /**
     * List of data to format
     */
    const FORMAT_COMMON = 1;
    const FORMAT_LANG   = 2;
    const FORMAT_SHOP   = 3;

    /**
     * List of association types
     */
    const HAS_ONE  = 1;
    const HAS_MANY = 2;
    const BELONGS_TO_MANY = 3;

    /**
     * List of common database default values
     */
    const DEFAULT_NULL = '@@NULL';
    const DEFAULT_CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';

    /**
     * List of database column sizes
     */
    const SIZE_MAX_VARCHAR = 255;
    const SIZE_MEDIUM_TEXT = 16777215;
    const SIZE_TEXT = 65535;
    const SIZE_LONG_TEXT = 4294967295;

    const SIZE_REFERENCE = 64;

    /**
     * List of different database key types
     */
    const PRIMARY_KEY = 1;
    const UNIQUE_KEY = 2;
    const FOREIGN_KEY = 3;
    const KEY = 4;

    /** @var int|null Object ID */
    public $id;

    /** @var int|null Language ID */
    public $id_lang = null;

    /** @var int|null Shop ID */
    public $id_shop = null;

    /** @var array|null List of shop IDs */
    public $id_shop_list = null;

    /** @var bool */
    protected $get_shop_from_context = true;

    /** @var array|null Holds required fields for each ObjectModel class */
    protected static $fieldsRequiredDatabase = null;

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var string
     */
    protected $table;

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var string
     */
    protected $identifier;

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var array
     */
    protected $fieldsRequired = [];

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var array
     */
    protected $fieldsSize = [];

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var array
     */
    protected $fieldsValidate = [];

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var array
     */
    protected $fieldsRequiredLang = [];

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var array
     */
    protected $fieldsSizeLang = [];

    /**
     * @deprecated 1.0.0 Define property using $definition['table'] property instead.
     * @var array
     */
    protected $fieldsValidateLang = [];

    /**
     * @deprecated 1.0.0
     * @var array
     */
    protected $tables = [];

    /**
     * @var array Webservice parameters
     */
    protected $webserviceParameters = [];

    /** @var string|null Path to image directory. Used for image deletion. */
    protected $image_dir = null;

    /** @var String file type of image files. */
    protected $image_format;

    /**
     * @var array Contains object definition
     */
    public static $definition = [];

    /**
     * Holds compiled definitions of each ObjectModel class.
     * Values are assigned during object initialization.
     *
     * @var array
     */
    protected static $loaded_classes = [];

    /** @var array Contains current object definition. */
    protected $def;

    /** @var array|null List of specific fields to update (all fields if null). */
    protected $update_fields = null;

    /** @var Db An instance of the db in order to avoid calling Db::getInstance() thousands of times. */
    protected static $db = false;

    /** @var bool Enables to define an ID before adding object. */
    public $force_id = false;

    /**
     * @var bool If true, objects are cached in memory.
     */
    protected static $cache_objects = true;

    /**
     * @return string|null
     */
    public static function getRepositoryClassName()
    {
        return null;
    }

    /**
     * Returns object validation rules (fields validity)
     *
     * @param string $class Child class name for static use (optional)
     *
     * @return array Validation rules (fields validity)
     */
    public static function getValidationRules($class = __CLASS__)
    {
        $object = new $class();

        return [
            'required'     => $object->fieldsRequired,
            'size'         => $object->fieldsSize,
            'validate'     => $object->fieldsValidate,
            'requiredLang' => $object->fieldsRequiredLang,
            'sizeLang'     => $object->fieldsSizeLang,
            'validateLang' => $object->fieldsValidateLang,
        ];
    }

    /**
     * Builds the object
     *
     * @param int|null $id If specified, loads and existing object from DB (optional).
     * @param int|null $idLang Required if object is multilingual (optional).
     * @param int|null $idShop ID shop for objects with multishop tables.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        $className = get_class($this);
        if (!isset(ObjectModel::$loaded_classes[$className])) {
            $this->def = ObjectModel::getDefinition($className);
            $this->setDefinitionRetrocompatibility();
            if (!Validate::isTableOrIdentifier($this->def['primary']) || !Validate::isTableOrIdentifier($this->def['table'])) {
                throw new PrestaShopException('Identifier or table format not valid for class '.$className);
            }

            ObjectModel::$loaded_classes[$className] = get_object_vars($this);
        } else {
            foreach (ObjectModel::$loaded_classes[$className] as $key => $value) {
                $this->{$key} = $value;
            }
        }

        if ($idLang !== null) {
            $this->id_lang = (Language::getLanguage($idLang) !== false) ? $idLang : Configuration::get('PS_LANG_DEFAULT');
        }

        if ($idShop && $this->isMultishop()) {
            $this->id_shop = (int) $idShop;
            $this->get_shop_from_context = false;
        }

        if ($this->isMultishop() && !$this->id_shop) {
            $this->id_shop = Context::getContext()->shop->id;
        }

        if ($id) {
            /** @var Adapter_EntityMapper $entityMapper */
            $entityMapper = Adapter_ServiceLocator::get("Adapter_EntityMapper");
            $entityMapper->load($id, $idLang, $this, $this->def, $this->id_shop, static::$cache_objects);
        }

        $this->image_format = ImageManager::getDefaultImageExtension();
    }

    /**
     * thirty bees' new coding style dictates that camelCase should be used
     * rather than snake_case
     * These magic methods provide backwards compatibility for modules/themes/whatevers
     * that still access properties via their snake_case names
     *
     * @param string $property Property name
     *
     * @return mixed
     */
    public function &__get($property)
    {
        // Property to camelCase for backwards compatibility
        $camelCaseProperty = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $property))));
        if (property_exists($this, $camelCaseProperty)) {
            return $this->$camelCaseProperty;
        }

        return $this->$property;
    }

    /**
     * thirty bees' new coding style dictates that camelCase should be used
     * rather than snake_case
     * These magic methods provide backwards compatibility for modules/themes/whatevers
     * that still access properties via their snake_case names
     *
     * @param string $property
     * @param mixed $value
     *
     * @return void
     */
    public function __set($property, $value)
    {
        // Property to camelCase for backwards compatibility
        $snakeCaseProperty = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $property))));
        if (property_exists($this, $snakeCaseProperty)) {
            $this->$snakeCaseProperty = $value;
        } else {
            $this->$property = $value;
        }
    }

    /**
     * Prepare fields for ObjectModel class (add, update)
     * All fields are verified (pSQL, intval, ...)
     *
     * @return array All object fields
     * @throws PrestaShopException
     */
    public function getFields()
    {
        $this->validateFields();
        $fields = $this->formatFields(static::FORMAT_COMMON);

        // For retro compatibility
        if (Shop::isTableAssociated($this->def['table'])) {
            $fields = array_merge($fields, $this->getFieldsShop());
        }

        // Ensure that we get something to insert
        if (!$fields && isset($this->id) && Validate::isUnsignedId($this->id)) {
            $fields[$this->def['primary']] = $this->id;
        }

        return $fields;
    }

    /**
     * Return fields that are stored in object model primary table
     * Fields are sanitized (pSQL, intval, ...)
     *
     * @return array primary table fields
     * @throws PrestaShopException
     */
    protected function getFieldsPrimary()
    {
        // although it would be better from performance point of view to build list of primary table fields directly
        // by calling formatFields method, we can't do that.
        // The reason is that some subclasses overridden getFields() method to include additional fields
        $fields = $this->getFields();
        $definitions = $this->def['fields'];
        foreach ($fields as $field => $value) {
            $shopOnlyField = isset($definitions[$field]['shopOnly']) && $definitions[$field]['shopOnly'];
            if ($shopOnlyField) {
                unset($fields[$field]);
            }
        }
        return $fields;
    }

    /**
     * Prepare fields for multishop
     * Fields are not validated here, we consider they are already validated in getFields() method,
     * this is not the best solution but this is the only one possible for retro compatibility.
     *
     * @return array All object fields
     *
     * @throws PrestaShopException
     */
    public function getFieldsShop()
    {
        $fields = $this->formatFields(static::FORMAT_SHOP);
        if (!$fields && isset($this->id) && Validate::isUnsignedId($this->id)) {
            $fields[$this->def['primary']] = $this->id;
        }

        return $fields;
    }

    /**
     * Prepare multilang fields
     *
     * @return array
     * @throws PrestaShopException
     */
    public function getFieldsLang()
    {
        // Backward compatibility
        if (method_exists($this, 'getTranslationsFieldsChild')) {
            return $this->getTranslationsFieldsChild();
        }

        $this->validateFieldsLang();
        $isLangMultishop = $this->isLangMultishop();

        $fields = [];
        if ($this->id_lang === null) {
            foreach (Language::getIDs(false) as $idLang) {
                $fields[$idLang] = $this->formatFields(static::FORMAT_LANG, $idLang);
                $fields[$idLang]['id_lang'] = $idLang;
                if ($this->id_shop && $isLangMultishop) {
                    $fields[$idLang]['id_shop'] = (int) $this->id_shop;
                }
            }
        } else {
            $fields = [$this->id_lang => $this->formatFields(static::FORMAT_LANG, $this->id_lang)];
            $fields[$this->id_lang]['id_lang'] = $this->id_lang;
            if ($this->id_shop && $isLangMultishop) {
                $fields[$this->id_lang]['id_shop'] = (int) $this->id_shop;
            }
        }

        return $fields;
    }

    /**
     * Formats values of each fields.
     *
     * @param int $type FORMAT_COMMON or FORMAT_LANG or FORMAT_SHOP
     * @param int $idLang If this parameter is given, only take lang fields
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    protected function formatFields($type, $idLang = null)
    {
        $fields = [];

        // Set primary key in fields
        if (isset($this->id)) {
            $fields[$this->def['primary']] = $this->id;
        }

        foreach ($this->def['fields'] as $field => $data) {

            $langField = isset($data['lang']) && $data['lang'];
            if ($type == static::FORMAT_LANG && !$langField) {
                continue;
            }

            $shopOnlyField = isset($data['shopOnly']) && $data['shopOnly'];
            $shopField = $shopOnlyField || isset($data['shop']) && $data['shop'];
            if ($type == static::FORMAT_SHOP && !$shopField) {
                continue;
            }

            if ($type == static::FORMAT_COMMON && ($shopOnlyField || $langField)) {
                continue;
            }

            if (is_array($this->update_fields)) {
                if (($langField || $shopField) && (empty($this->update_fields[$field]) || ($type == static::FORMAT_LANG && empty($this->update_fields[$field][$idLang])))) {
                    continue;
                }
            }

            // Get field value, if value is multilang and field is empty, use value from default lang
            $value = $this->$field;
            if ($type == static::FORMAT_LANG && $idLang && is_array($value)) {
                if (!empty($value[$idLang])) {
                    $value = $value[$idLang];
                } elseif (!empty($data['required'])) {
                    $value = $value[Configuration::get('PS_LANG_DEFAULT')];
                } else {
                    $value = '';
                }
            }

            $purify = isset($data['validate']) && mb_strtolower($data['validate']) == 'iscleanhtml';
            // Format field value
            $fields[$field] = ObjectModel::formatValue($value, $data['type'], false, $purify, !empty($data['allow_null']));
        }

        return $fields;
    }

    /**
     * Formats a value
     *
     * @param mixed $value
     * @param int $type
     * @param bool $withQuotes
     * @param bool $purify
     * @param bool $allowNull
     *
     * @return int|float|bool|string|array
     *
     * @throws PrestaShopException
     */
    public static function formatValue($value, $type, $withQuotes = false, $purify = true, $allowNull = false)
    {
        if ($allowNull && $value === null) {
            return ['type' => 'sql', 'value' => 'NULL'];
        }

        switch ($type) {
            case self::TYPE_INT:
            case self::TYPE_BOOL:
                return (int) $value;

            case self::TYPE_FLOAT:
            case self::TYPE_PRICE:
                return Tools::parseNumber($value);

            case self::TYPE_DATE:
                if (!$value) {
                    return '0000-00-00';
                }

                if ($withQuotes) {
                    return '\''.pSQL($value).'\'';
                }
                return pSQL($value);

            case self::TYPE_HTML:
                if ($purify) {
                    $value = Tools::purifyHTML($value);
                }
                if ($withQuotes) {
                    return '\''.pSQL($value, true).'\'';
                }
                return pSQL($value, true);

            case self::TYPE_SQL:
                if ($withQuotes) {
                    return '\''.pSQL($value, true).'\'';
                }
                return pSQL($value, true);

            case self::TYPE_NOTHING:
                return $value;

            case self::TYPE_STRING:
            default :
                if ($withQuotes) {
                    return '\''.pSQL($value).'\'';
                }
                return pSQL($value);
        }
    }

    /**
     * Saves current object to database (add or update)
     *
     * @param bool $nullValues
     * @param bool $autoDate
     *
     * @return bool Insertion result
     * @throws PrestaShopException
     */
    public function save($nullValues = false, $autoDate = true)
    {
        return (int) $this->id > 0 ? $this->update($nullValues) : $this->add($autoDate, $nullValues);
    }

    /**
     * Adds current object to the database
     *
     * @param bool $autoDate
     * @param bool $nullValues
     *
     * @return bool Insertion result
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function add($autoDate = true, $nullValues = false)
    {
        if (isset($this->id) && !$this->force_id) {
            unset($this->id);
        }

        // @hook actionObject*AddBefore
        Hook::triggerEvent('actionObjectAddBefore', ['object' => $this]);
        Hook::triggerEvent('actionObject'.get_class($this).'AddBefore', ['object' => $this]);

        // Automatically fill dates
        if ($autoDate && property_exists($this, 'date_add')) {
            $this->date_add = date('Y-m-d H:i:s');
        }
        if ($autoDate && property_exists($this, 'date_upd')) {
            $this->date_upd = date('Y-m-d H:i:s');
        }

        if (Shop::isTableAssociated($this->def['table'])) {
            if (is_array($this->id_shop_list) && count($this->id_shop_list)) {
                $idShopList = $this->id_shop_list;
            } else {
                $idShopList = Shop::getContextListShopID();
            }

            if (Shop::checkIdShopDefault($this->def['table']) && property_exists($this, 'id_shop_default')) {
                $defaultShopId = (int)Configuration::get('PS_SHOP_DEFAULT');
                $this->id_shop_default = in_array($defaultShopId, $idShopList) ? $defaultShopId : min($idShopList);
            }
        }

        // Database insertion
        $fields = $this->getFieldsPrimary();
        $conn = Db::getInstance();
        if (! $conn->insert($this->def['table'], $fields, $nullValues)) {
            return false;
        }

        // Get object id in database
        $this->id = $conn->Insert_ID();

        $result = true;
        // Database insertion for multishop fields related to the object
        if (Shop::isTableAssociated($this->def['table'])) {
            $fields = $this->getFieldsShop();
            $fields[$this->def['primary']] = (int) $this->id;

            foreach ($idShopList as $idShop) {
                $fields['id_shop'] = (int) $idShop;
                $result = $conn->insert($this->def['table'].'_shop', $fields, $nullValues) && $result;
            }
        }

        if (!$result) {
            return false;
        }

        // Database insertion for multilingual fields related to the object
        if (!empty($this->def['multilang'])) {
            $fields = $this->getFieldsLang();
            if ($fields && is_array($fields)) {
                $shops = Shop::getCompleteListOfShopsID();
                $asso = Shop::getAssoTable($this->def['table'].'_lang');
                foreach ($fields as $field) {
                    foreach (array_keys($field) as $key) {
                        if (!Validate::isTableOrIdentifier($key)) {
                            throw new PrestaShopException('key '.$key.' is not table or identifier');
                        }
                    }
                    $field[$this->def['primary']] = (int) $this->id;

                    if ($asso !== false && $asso['type'] == 'fk_shop') {
                        foreach ($shops as $idShop) {
                            $field['id_shop'] = (int) $idShop;
                            $result = $conn->insert($this->def['table'].'_lang', $field) && $result;
                        }
                    } else {
                        $result = $conn->insert($this->def['table'].'_lang', $field) && $result;
                    }
                }
            }
        }

        // @hook actionObject*AddAfter
        Hook::triggerEvent('actionObjectAddAfter', ['object' => $this]);
        Hook::triggerEvent('actionObject'.get_class($this).'AddAfter', ['object' => $this]);

        return $result;
    }

    /**
     * Takes current object ID, gets its values from database,
     * saves them in a new row and loads newly saved values as a new object.
     *
     * @return ObjectModel|false
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function duplicateObject()
    {
        $definition = ObjectModel::getDefinition($this);
        $conn = Db::getInstance();

        $res = $conn->getRow('
					SELECT *
					FROM `'._DB_PREFIX_.bqSQL($definition['table']).'`
					WHERE `'.bqSQL($definition['primary']).'` = '.(int) $this->id
                );
        if (!$res) {
            return false;
        }

        unset($res[$definition['primary']]);
        foreach ($res as $field => &$value) {
            if (isset($definition['fields'][$field])) {
                $value = ObjectModel::formatValue($value, $definition['fields'][$field]['type'], false, true, !empty($definition['fields'][$field]['allow_null']));
            }
        }

        if (!$conn->insert($definition['table'], $res)) {
            return false;
        }

        $objectId = $conn->Insert_ID();

        if (isset($definition['multilang']) && $definition['multilang']) {
            $result = $conn->getArray('
			SELECT *
			FROM `'._DB_PREFIX_.bqSQL($definition['table']).'_lang`
			WHERE `'.bqSQL($definition['primary']).'` = '.(int) $this->id);
            if (!$result) {
                return false;
            }

            foreach ($result as &$row) {
                foreach ($row as $field => &$value) {
                    if (isset($definition['fields'][$field])) {
                        $value = ObjectModel::formatValue($value, $definition['fields'][$field]['type'], false, true, !empty($definition['fields'][$field]['allow_null']));
                    }
                }
            }

            // Keep $row2, you cannot use $row because there is an unexplicated conflict with the previous usage of this variable
            foreach ($result as $row2) {
                $row2[$definition['primary']] = (int) $objectId;
                if (!$conn->insert($definition['table'].'_lang', $row2)) {
                    return false;
                }
            }
        }

        $className = $definition['classname'];
        /** @var ObjectModel $objectDuplicated */
        $objectDuplicated = new $className((int) $objectId);
        $objectDuplicated->duplicateShops((int) $this->id);

        return $objectDuplicated;
    }

    /**
     * Updates the current object in the database
     *
     * @param bool $nullValues
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function update($nullValues = false)
    {
        $id = (int)$this->id;

        if (!$id) {
            trigger_error("Attempt to update unsaved object ".get_class($this), E_USER_WARNING);
            return false;
        }

        // @hook actionObject*UpdateBefore
        Hook::triggerEvent('actionObjectUpdateBefore', ['object' => $this]);
        Hook::triggerEvent('actionObject'.get_class($this).'UpdateBefore', ['object' => $this]);

        $this->clearCache();

        // Automatically fill dates
        if (property_exists($this, 'date_upd')) {
            $this->date_upd = date('Y-m-d H:i:s');
            if (isset($this->update_fields) && is_array($this->update_fields) && count($this->update_fields)) {
                $this->update_fields['date_upd'] = true;
            }
        }

        // Automatically fill dates
        if (property_exists($this, 'date_add') && $this->date_add == null) {
            $this->date_add = date('Y-m-d H:i:s');
            if (isset($this->update_fields) && is_array($this->update_fields) && count($this->update_fields)) {
                $this->update_fields['date_add'] = true;
            }
        }

        if (is_array($this->id_shop_list) && count($this->id_shop_list)) {
            $idShopList = $this->id_shop_list;
        } else {
            $idShopList = Shop::getContextListShopID();
        }

        if (Shop::checkIdShopDefault($this->def['table']) && property_exists($this, 'id_shop_default') && !$this->id_shop_default) {
            $defaultShopId = (int)Configuration::get('PS_SHOP_DEFAULT');
            $this->id_shop_default = in_array($defaultShopId, $idShopList) ? $defaultShopId : min($idShopList);
        }

        // Database update
        $primaryFields = $this->getFieldsPrimary();
        $conn = Db::getInstance();
        if (!$result = $conn->update($this->def['table'], $primaryFields, '`'.pSQL($this->def['primary']).'` = '.$id, 0, $nullValues)) {
            return false;
        }

        // Database insertion for multishop fields related to the object
        if (Shop::isTableAssociated($this->def['table'])) {

            // for insert operation we need all multishop fields
            $insertFields = $this->getFieldsShop();
            $insertFields[$this->def['primary']] = $id;

            // by default update all fields except primary key
            $updateFields = $insertFields;
            unset($updateFields[$this->def['primary']]);
            unset($updateFields['id_shop']);

            // if property $update_fields exists, we have to use it to restrict update fields
            if (is_array($this->update_fields)) {
                foreach ($updateFields as $key => $val) {
                    if (!array_key_exists($key, $this->update_fields)) {
                        unset($updateFields[$key]);
                    }
                }
            }

            // update or create multishop entries
            foreach ($idShopList as $idShop) {
                $where = $this->def['primary'].' = '.$id .' AND id_shop = '.(int) $idShop;

                $shopEntryExists = $conn->getValue('SELECT '.$this->def['primary'].' FROM '._DB_PREFIX_.$this->def['table'].'_shop WHERE '.$where);
                if ($shopEntryExists) {
                    // if multishop db entry exists, we use $updateFields array to update it
                    $result = $conn->update($this->def['table'].'_shop', $updateFields, $where, 0, $nullValues) && $result;
                } elseif (Shop::getContext() == Shop::CONTEXT_SHOP) {
                    // if multishop db entry doesnt exist yet, we use $insertFields array to create it
                    $insertFields['id_shop'] = (int) $idShop;
                    $result = $conn->insert($this->def['table'].'_shop', $insertFields, $nullValues) && $result;
                }
            }
        }

        // Database update for multilingual fields related to the object
        if (isset($this->def['multilang']) && $this->def['multilang']) {
            $fields = $this->getFieldsLang();
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    foreach (array_keys($field) as $key) {
                        if (!Validate::isTableOrIdentifier($key)) {
                            throw new PrestaShopException('key '.$key.' is not a valid table or identifier');
                        }
                    }

                    // If this table is linked to multishop system, update / insert for all shops from context
                    if ($this->isLangMultishop()) {
                        foreach ($idShopList as $idShop) {
                            $field['id_shop'] = (int) $idShop;
                            $where = pSQL($this->def['primary']).' = '.$id .' AND id_lang = '.(int) $field['id_lang'].' AND id_shop = '.(int) $idShop;

                            if ($conn->getValue('SELECT COUNT(*) FROM '.pSQL(_DB_PREFIX_.$this->def['table']).'_lang WHERE '.$where)) {
                                $result = $conn->update($this->def['table'].'_lang', $field, $where) && $result;
                            } else {
                                $result = $conn->insert($this->def['table'].'_lang', $field) && $result;
                            }
                        }
                    } else {
                        // If this table is not linked to multishop system ...
                        $where = pSQL($this->def['primary']).' = '.$id .' AND id_lang = '.(int) $field['id_lang'];
                        if ($conn->getValue('SELECT COUNT(*) FROM '.pSQL(_DB_PREFIX_.$this->def['table']).'_lang WHERE '.$where)) {
                            $result = $conn->update($this->def['table'].'_lang', $field, $where) && $result;
                        } else {
                            $result = $conn->insert($this->def['table'].'_lang', $field, $nullValues) && $result;
                        }
                    }
                }
            }
        }

        // @hook actionObject*UpdateAfter
        Hook::triggerEvent('actionObjectUpdateAfter', ['object' => $this]);
        Hook::triggerEvent('actionObject'.get_class($this).'UpdateAfter', ['object' => $this]);

        return $result;
    }

    /**
     * Deletes current object from database
     *
     * @return bool True if delete was successful
     * @throws PrestaShopException
     */
    public function delete()
    {
        // @hook actionObject*DeleteBefore
        Hook::triggerEvent('actionObjectDeleteBefore', ['object' => $this]);
        Hook::triggerEvent('actionObject'.get_class($this).'DeleteBefore', ['object' => $this]);

        $this->clearCache();
        $result = true;
        // Remove association to multishop table
        $conn = Db::getInstance();
        if (Shop::isTableAssociated($this->def['table'])) {
            if (is_array($this->id_shop_list) && count($this->id_shop_list)) {
                $idShopList = $this->id_shop_list;
            } else {
                $idShopList = Shop::getContextListShopID();
            }

            $result = $conn->delete($this->def['table'].'_shop', '`'.$this->def['primary'].'`='.(int) $this->id.' AND id_shop IN ('.implode(', ', $idShopList).')');
        }

        // Database deletion
        $hasMultishopEntries = $this->hasMultishopEntries();
        if ($result && !$hasMultishopEntries) {
            $result = $conn->delete($this->def['table'], '`'.bqSQL($this->def['primary']).'` = '.(int) $this->id);
        }

        if (!$result) {
            return false;
        }

        // Database deletion for multilingual fields related to the object
        if (!empty($this->def['multilang']) && !$hasMultishopEntries) {
            $result = $conn->delete($this->def['table'].'_lang', '`'.bqSQL($this->def['primary']).'` = '.(int) $this->id);
        }

        // @hook actionObject*DeleteAfter
        Hook::triggerEvent('actionObjectDeleteAfter', ['object' => $this]);
        Hook::triggerEvent('actionObject'.get_class($this).'DeleteAfter', ['object' => $this]);

        return $result;
    }

    /**
     * Deletes multiple objects from the database at once
     *
     * @param array $ids Array of objects IDs.
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function deleteSelection($ids)
    {
        $result = true;
        foreach ($ids as $id) {
            $this->id = (int) $id;
            $result = $result && $this->delete();
        }

        return $result;
    }

    /**
     * Toggles object status in database
     *
     * @return bool Update result
     * @throws PrestaShopException
     */
    public function toggleStatus()
    {
        // Object must have a variable called 'active'
        if (!property_exists($this, 'active')) {
            throw new PrestaShopException('property "active" is missing in object '.get_class($this));
        }

        // Update only active field
        $this->setFieldsToUpdate(['active' => true]);

        // Update active status on object
        $this->active = !(int) $this->active;

        // Change status to active/inactive
        return $this->update(false);
    }

    /**
     * @deprecated 1.0.0 (use getFieldsLang())
     *
     * @param array $fieldsArray
     *
     * @return array
     * @throws PrestaShopException
     */
    protected function getTranslationsFields($fieldsArray)
    {
        $fields = [];

        if ($this->id_lang == null) {
            foreach (Language::getIDs(false) as $id_lang) {
                $this->makeTranslationFields($fields, $fieldsArray, $id_lang);
            }
        } else {
            $this->makeTranslationFields($fields, $fieldsArray, $this->id_lang);
        }

        return $fields;
    }

    /**
     * @deprecated 1.0.0
     *
     * @param array $fields
     * @param array $fieldsArray
     * @param int $idLanguage
     *
     * @throws PrestaShopException
     */
    protected function makeTranslationFields(&$fields, &$fieldsArray, $idLanguage)
    {
        $fields[$idLanguage]['id_lang'] = $idLanguage;
        $fields[$idLanguage][$this->def['primary']] = (int) $this->id;
        if ($this->id_shop && $this->isLangMultishop()) {
            $fields[$idLanguage]['id_shop'] = (int) $this->id_shop;
        }
        foreach ($fieldsArray as $k => $field) {
            $html = false;
            $fieldName = $field;
            if (is_array($field)) {
                $fieldName = $k;
                $html = (isset($field['html'])) ? $field['html'] : false;
            }

            /* Check fields validity */
            if (!Validate::isTableOrIdentifier($fieldName)) {
                throw new PrestaShopException('identifier is not table or identifier : '.$fieldName);
            }

            // Copy the field, or the default language field if it's both required and empty
            if ((!$this->id_lang && isset($this->{$fieldName}[$idLanguage]) && !empty($this->{$fieldName}[$idLanguage]))
            || ($this->id_lang && !empty($this->$fieldName))) {
                $fields[$idLanguage][$fieldName] = $this->id_lang ? pSQL($this->$fieldName, $html) : pSQL($this->{$fieldName}[$idLanguage], $html);
            } elseif (in_array($fieldName, $this->fieldsRequiredLang)) {
                $fields[$idLanguage][$fieldName] = pSQL($this->id_lang ? $this->$fieldName : $this->{$fieldName}[Configuration::get('PS_LANG_DEFAULT')], $html);
            } else {
                $fields[$idLanguage][$fieldName] = '';
            }
        }
    }

    /**
     * Checks if object field values are valid before database interaction
     *
     * @param bool $die
     * @param bool $errorReturn
     *
     * @return bool|string True, false or error message.
     * @throws PrestaShopException
     */
    public function validateFields($die = true, $errorReturn = false)
    {
        foreach ($this->def['fields'] as $field => $data) {
            if (!empty($data['lang'])) {
                continue;
            }

            if (is_array($this->update_fields) && empty($this->update_fields[$field]) && isset($this->def['fields'][$field]['shop']) && $this->def['fields'][$field]['shop']) {
                continue;
            }

            $message = $this->validateField($field, $this->$field);
            if ($message !== true) {
                if ($die) {
                    throw new PrestaShopException($message);
                }

                return $errorReturn ? $message : false;
            }
        }

        return true;
    }

    /**
     * Checks if multilingual object field values are valid before database interaction.
     *
     * @param bool $die
     * @param bool $errorReturn
     *
     * @return bool|string True, false or error message.
     * @throws PrestaShopException
     */
    public function validateFieldsLang($die = true, $errorReturn = false)
    {
        $idLangDefault = Configuration::get('PS_LANG_DEFAULT');

        foreach ($this->def['fields'] as $field => $data) {
            if (empty($data['lang'])) {
                continue;
            }

            $values = $this->$field;

            // If the object has not been loaded in multilanguage, then the value is the one for the current language of the object
            if (!is_array($values)) {
                $values = [$this->id_lang => $values];
            }

            // The value for the default must always be set, so we put an empty string if it does not exists
            if (!isset($values[$idLangDefault])) {
                $values[$idLangDefault] = '';
            }

            foreach ($values as $idLang => $value) {
                if (is_array($this->update_fields) && empty($this->update_fields[$field][$idLang])) {
                    continue;
                }

                $message = $this->validateField($field, $value, $idLang);
                if ($message !== true) {
                    if ($die) {
                        throw new PrestaShopException($message);
                    }

                    return $errorReturn ? $message : false;
                }
            }
        }

        return true;
    }

    /**
     * Validate a single field
     *
     * @param string $field Field name
     * @param array|bool|float|int|string|null $value Field value
     * @param int|null $idLang Language ID
     * @param array $skip Array of fields to skip.
     * @param bool $humanErrors If true, uses more descriptive, translatable error strings.
     *
     * @return true|string True or error message string.
     * @throws PrestaShopException
     */
    public function validateField($field, $value, $idLang = null, $skip = [], $humanErrors = false)
    {
        static $psLangDefault = null;
        static $psAllowHtmlIframe = null;

        if ($psLangDefault === null) {
            $psLangDefault = Configuration::get('PS_LANG_DEFAULT');
        }

        if ($psAllowHtmlIframe === null) {
            $psAllowHtmlIframe = (int) Configuration::get('PS_ALLOW_HTML_IFRAME');
        }


        $this->cacheFieldsRequiredDatabase();
        $data = $this->def['fields'][$field];



        // Check if field is required
        $requiredFields = (isset(static::$fieldsRequiredDatabase[get_class($this)])) ? static::$fieldsRequiredDatabase[get_class($this)] : [];
        if (!$idLang || $idLang == $psLangDefault) {
            if (!in_array('required', $skip) && (!empty($data['required']) || in_array($field, $requiredFields))) {
                if (Tools::isEmpty($value)) {
                    if ($humanErrors) {
                        return sprintf(Tools::displayError('The %s field is required.'), $this->displayFieldName($field, get_class($this)));
                    } else {
                        return 'Property '.get_class($this).'->'.$field.' is empty';
                    }
                }
            }
        }

        // Default value
        if (!$value && !empty($data['default'])) {
            $value = $data['default'];
            $this->$field = $value;
        }

        // Check field values
        if (!in_array('values', $skip) && !empty($data['values']) && is_array($data['values']) && !in_array($value, $data['values'])) {
            if ($humanErrors) {
                return sprintf(Tools::displayError('The %s field is invalid.'), $this->displayFieldName($field, get_class($this)));
            } else {
                return 'Property '.get_class($this).'->'.$field.' has invalid value [' . ErrorUtils::displayArgument($value) . ']. Allowed values are: '.implode(', ', $data['values']).')';
            }
        }

        // Check field size
        if (!in_array('size', $skip) && !empty($data['size']) && in_array($data['type'], [static::TYPE_STRING, static::TYPE_HTML])) {
            $size = $data['size'];
            if (!is_array($data['size'])) {
                $size = ['min' => 0, 'max' => $data['size']];
            }

            $length = is_null($value) ? 0 : mb_strlen($value);
            if ($length < $size['min'] || $length > $size['max']) {
                if ($humanErrors) {
                    if (isset($data['lang']) && $data['lang']) {
                        $language = new Language((int) $idLang);

                        return sprintf(Tools::displayError('The field %1$s (%2$s) is too long (%3$d chars max, html chars including).'), $this->displayFieldName($field, get_class($this)), $language->name, $size['max']);
                    } else {
                        return sprintf(Tools::displayError('The %1$s field is too long (%2$d chars max).'), $this->displayFieldName($field, get_class($this)), $size['max']);
                    }
                } else {
                    return 'Property '.get_class($this).'->'.$field.' length ('.$length.') must be between '.$size['min'].' and '.$size['max'];
                }
            }
        }

        // Check field validator
        if (!in_array('validate', $skip) && !empty($data['validate'])) {
            if (!empty($value)) {
                $validate = $data['validate'];
                if (is_string($validate)) {
                    if (mb_strtolower($validate) === 'iscleanhtml') {
                        $res = Validate::isCleanHtml($value, $psAllowHtmlIframe);
                    } elseif (method_exists(Validate::class, $validate)) {
                        $res = (bool)Validate::$validate($value);
                    } else {
                        throw new PrestaShopException('Property '.get_class($this).'->'.$field.': Validation function not found: '.$validate);
                    }
                } elseif (is_callable($validate)) {
                    $res = $validate($value);
                } else {
                    throw new PrestaShopException('Property '.get_class($this).'->'.$field.': invalid validation callback');
                }

                if (!$res) {
                    if ($humanErrors) {
                        return sprintf(Tools::displayError('The %s field is invalid.'), $this->displayFieldName($field, get_class($this)));
                    } else {
                        return 'Property '.get_class($this).'->'.$field.' has invalid value [' . ErrorUtils::displayArgument($value) . ']';
                    }
                }
            }
        }

        return true;
    }

    /**
     * Returns field name translation
     *
     * @param string $field Field name
     * @param string $class ObjectModel class name
     * @param bool $htmlentities If true, applies htmlentities() to result string
     * @param Context|null $context Context object
     *
     * @return string
     */
    public static function displayFieldName($field, $class = __CLASS__, $htmlentities = true, ?Context $context = null)
    {
        global $_FIELDS;

        if (!isset($context)) {
            $context = Context::getContext();
        }

        if ($_FIELDS === null && file_exists(_PS_TRANSLATIONS_DIR_.$context->language->iso_code.'/fields.php')) {
            include_once(_PS_TRANSLATIONS_DIR_.$context->language->iso_code.'/fields.php');
        }

        $key = $class.'_'.md5($field);

        if (is_array($_FIELDS) && array_key_exists($key, $_FIELDS) && $_FIELDS[$key] !== '') {
            $str = $_FIELDS[$key];
            return $htmlentities ? htmlentities($str, ENT_QUOTES, 'utf-8') : $str;
        }

        return $field;
    }

    /**
     * @param bool $htmlentities
     *
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @deprecated 1.0.0 Use validateController() instead
     */
    public function validateControler($htmlentities = true)
    {
        Tools::displayAsDeprecated();

        return $this->validateController($htmlentities);
    }

    /**
     * Validates submitted values and returns an array of errors, if any.
     *
     * @param bool $htmlentities If true, uses htmlentities() for field name translations in errors.
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function validateController($htmlentities = true)
    {
        $this->cacheFieldsRequiredDatabase();
        $errors = [];
        $className = get_class($this);
        $requiredFieldsDatabase = static::$fieldsRequiredDatabase[$className] ?? [];

        foreach ($this->def['fields'] as $field => $data) {
            $value = Tools::getValue($field, $this->{$field});
            // Check if field is required by user
            if (in_array($field, $requiredFieldsDatabase)) {
                $data['required'] = true;
            }

            $isEmpty = empty($value) && $value !== '0' && $value !== 0 && $value !== 0.0 && $value !== false;

            // Checking for required fields
            if (isset($data['required']) && $data['required'] && $isEmpty) {
                if (!$this->id || $field != 'passwd') {
                    $errors[$field] = '<b>'.static::displayFieldName($field, $className, $htmlentities).'</b> '.Tools::displayError('is required.');
                }
            }

            // Checking for maximum fields sizes
            if (isset($data['size']) && !$isEmpty && in_array($data['type'], [static::TYPE_STRING, static::TYPE_HTML]) && mb_strlen($value) > $data['size']) {
                $errors[$field] = sprintf(
                    Tools::displayError('%1$s is too long. Maximum length: %2$d'),
                    static::displayFieldName($field, $className, $htmlentities),
                    $data['size']
                );
            }

            // Checking for fields validity
            // Hack for postcode required for country which does not have postcodes
            if (!$isEmpty || ($field == 'postcode' && $value == '0')) {
                $validationError = false;
                if (isset($data['validate'])) {
                    $dataValidate = $data['validate'];
                    if (!Validate::$dataValidate($value) && (!$isEmpty || $data['required'])) {
                        $errors[$field] = '<b>'.static::displayFieldName($field, $className, $htmlentities).
                            '</b> '.Tools::displayError('is invalid.');
                        $validationError = true;
                    }
                }

                if (!$validationError) {
                    if (isset($data['copy_post']) && !$data['copy_post']) {
                        continue;
                    }
                    if ($field == 'passwd') {
                        if ($value = Tools::getValue($field)) {
                            $this->{$field} = Tools::hash($value);
                        }
                    } else {
                        $this->{$field} = $value;
                    }
                }
            }
        }

        // call modules hook to validate controller
        foreach (['actionObjectValidateController', 'actionObject'.$className.'ValidateController'] as $hookName) {
            $modulesErrors = Hook::getResponses($hookName, ['object' => $this, 'className' => $className]);
            foreach ($modulesErrors as $moduleErrors) {
                if (is_array($moduleErrors)) {
                    foreach ($moduleErrors as $error) {
                        if (is_string($error)) {
                            $errors[] = $error;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Returns webservice parameters of this object.
     *
     * @param string|null $wsParamsAttributeName
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getWebserviceParameters($wsParamsAttributeName = null)
    {
        $this->cacheFieldsRequiredDatabase();
        $defaultResourceParameters = [
            'objectSqlId' => $this->def['primary'],
            'retrieveData' => [
                'className' => get_class($this),
                'retrieveMethod' => 'getWebserviceObjectList',
                'params' => [],
                'table' => $this->def['table'],
            ],
            'fields' => [
                'id' => ['sqlId' => $this->def['primary'], 'i18n' => false],
            ],
        ];

        if ($wsParamsAttributeName === null) {
            $wsParamsAttributeName = 'webserviceParameters';
        }

        if (!isset($this->{$wsParamsAttributeName}['objectNodeName'])) {
            $defaultResourceParameters['objectNodeName'] = $this->def['table'];
        }
        if (!isset($this->{$wsParamsAttributeName}['objectsNodeName'])) {
            $defaultResourceParameters['objectsNodeName'] = $this->def['table'].'s';
        }

        if (isset($this->{$wsParamsAttributeName}['associations'])) {
            foreach ($this->{$wsParamsAttributeName}['associations'] as $assocName => &$association) {
                if (!array_key_exists('setter', $association) || (isset($association['setter']) && !$association['setter'])) {
                    $association['setter'] = Tools::toCamelCase('set_ws_'.$assocName);
                }
                if (!array_key_exists('getter', $association)) {
                    $association['getter'] = Tools::toCamelCase('get_ws_'.$assocName);
                }
            }
        }

        if (isset($this->{$wsParamsAttributeName}['retrieveData']['retrieveMethod'])) {
            unset($defaultResourceParameters['retrieveData']['retrieveMethod']);
        }

        $resourceParameters = array_merge_recursive($defaultResourceParameters, $this->{$wsParamsAttributeName});

        $requiredFields = (static::$fieldsRequiredDatabase[get_class($this)] ?? []);
        foreach ($this->def['fields'] as $fieldName => $details) {
            if (!isset($resourceParameters['fields'][$fieldName])) {
                $resourceParameters['fields'][$fieldName] = [];
            }
            $currentField = [];
            $currentField['sqlId'] = $fieldName;
            if (isset($details['size'])) {
                $currentField['maxSize'] = $details['size'];
            }
            if (isset($details['lang'])) {
                $currentField['i18n'] = $details['lang'];
            } else {
                $currentField['i18n'] = false;
            }
            if ((isset($details['required']) && $details['required'] === true) || in_array($fieldName, $requiredFields)) {
                $currentField['required'] = true;
            } else {
                $currentField['required'] = false;
            }
            if (isset($details['validate'])) {
                $currentField['validateMethod'] = (
                                array_key_exists('validateMethod', $resourceParameters['fields'][$fieldName]) ?
                                array_merge($resourceParameters['fields'][$fieldName]['validateMethod'], [$details['validate']]) :
                                [$details['validate']]
                            );
            }
            $resourceParameters['fields'][$fieldName] = array_merge($resourceParameters['fields'][$fieldName], $currentField);

            if (isset($details['ws_modifier'])) {
                $resourceParameters['fields'][$fieldName]['modifier'] = $details['ws_modifier'];
            }
        }
        if (isset($this->date_add)) {
            $resourceParameters['fields']['date_add']['setter'] = false;
        }
        if (isset($this->date_upd)) {
            $resourceParameters['fields']['date_upd']['setter'] = false;
        }
        foreach ($resourceParameters['fields'] as $key => $resourceParametersField) {
            if (!isset($resourceParametersField['sqlId'])) {
                $resourceParameters['fields'][$key]['sqlId'] = $key;
            }
        }

        return $resourceParameters;
    }

    /**
     * Returns webservice object list.
     *
     * @param string $sqlJoin
     * @param string $sqlFilter
     * @param string $sqlSort
     * @param string $sqlLimit
     *
     * @return array|null
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function getWebserviceObjectList($sqlJoin, $sqlFilter, $sqlSort, $sqlLimit)
    {
        $assoc = Shop::getAssoTable($this->def['table']);
        if ($assoc !== false) {
            if ($assoc['type'] !== 'fk_shop') {
                $multiShopJoin = ' LEFT JOIN `'._DB_PREFIX_.bqSQL($this->def['table']).'_'.bqSQL($assoc['type']).'`
										AS `multi_shop_'.bqSQL($this->def['table']).'`
										ON (main.`'.bqSQL($this->def['primary']).'` = `multi_shop_'.bqSQL($this->def['table']).'`.`'.bqSQL($this->def['primary']).'`)';
                $sqlFilter = 'AND `multi_shop_'.bqSQL($this->def['table']).'`.id_shop = '.Context::getContext()->shop->id.' '.$sqlFilter;
                $sqlJoin = $multiShopJoin.' '.$sqlJoin;
            } else {
                $or = [];
                foreach (WebserviceRequest::getInstance()->getShopIds() as $idShop) {
                    $or[] = '(main.id_shop = ' . (int)$idShop . (isset($this->def['fields']['id_shop_group']) ? ' OR (id_shop = 0 AND id_shop_group=' . (int)Shop::getGroupFromShop((int)$idShop) . ')' : '') . ')';
                }

                $prepend = '';
                if ($or) {
                    $prepend = 'AND ('.implode('OR', $or).')';
                }
                $sqlFilter = $prepend.' '.$sqlFilter;
            }
        }
        $query = '
		SELECT DISTINCT main.`'.bqSQL($this->def['primary']).'` FROM `'._DB_PREFIX_.bqSQL($this->def['table']).'` AS main
		'.$sqlJoin.'
		WHERE 1 '.$sqlFilter.'
		'.($sqlSort != '' ? $sqlSort : '').'
		'.($sqlLimit != '' ? $sqlLimit : '');

        return Db::readOnly()->getArray($query);
    }

    /**
     * Validate required fields.
     *
     * @param bool $htmlentities
     *
     * @return array
     * @throws PrestaShopException
     */
    public function validateFieldsRequiredDatabase($htmlentities = true)
    {
        $this->cacheFieldsRequiredDatabase();
        $errors = [];
        $requiredFields = (isset(static::$fieldsRequiredDatabase[get_class($this)])) ? static::$fieldsRequiredDatabase[get_class($this)] : [];

        foreach ($this->def['fields'] as $field => $data) {
            if (!in_array($field, $requiredFields)) {
                continue;
            }

            if (!method_exists('Validate', $data['validate'])) {
                throw new PrestaShopException('Validation function not found. '.$data['validate']);
            }

            $value = Tools::getValue($field);

            if (empty($value)) {
                $errors[$field] = sprintf(Tools::displayError('The field %s is required.'), static::displayFieldName($field, get_class($this), $htmlentities));
            }
        }

        return $errors;
    }

    /**
     * Returns an array of required fields
     *
     * @param bool $all If true, returns required fields of all object classes.
     *
     * @return array|null
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function getFieldsRequiredDatabase($all = false)
    {
        return Db::readOnly()->getArray('
		SELECT id_required_field, object_name, field_name
		FROM '._DB_PREFIX_.'required_field
		'.(!$all ? 'WHERE object_name = \''.pSQL(get_class($this)).'\'' : ''));
    }

    /**
     * Caches data about required objects fields in memory
     *
     * @param bool $all If true, caches required fields of all object classes.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function cacheFieldsRequiredDatabase($all = true)
    {
        if (!is_array(static::$fieldsRequiredDatabase)) {
            $fields = $this->getfieldsRequiredDatabase((bool) $all);
            if ($fields) {
                foreach ($fields as $row) {
                    static::$fieldsRequiredDatabase[$row['object_name']][(int) $row['id_required_field']] = pSQL($row['field_name']);
                }
            } else {
                static::$fieldsRequiredDatabase = [];
            }
        }
    }

    /**
     * Sets required field for this class in the database.
     *
     * @param array $fields
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function addFieldsRequiredDatabase($fields)
    {
        if (!is_array($fields)) {
            return false;
        }

        $conn = Db::getInstance();
        if (!$conn->execute('DELETE FROM '._DB_PREFIX_.'required_field WHERE object_name = \''.get_class($this).'\'')) {
            return false;
        }

        foreach ($fields as $field) {
            if (!$conn->insert('required_field', ['object_name' => get_class($this), 'field_name' => pSQL($field)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clears cache entries that have this object's ID.
     *
     * @param bool $all If true, clears cache for all objects
     */
    public function clearCache($all = false)
    {
        if ($all) {
            Cache::clean('objectmodel_'.$this->def['classname'].'_*');
        } elseif ($this->id) {
            Cache::clean('objectmodel_'.$this->def['classname'].'_'.(int) $this->id.'_*');
        }
    }

    /**
     * Checks if current object is associated to a shop.
     *
     * @param int|null $idShop
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function isAssociatedToShop($idShop = null)
    {
        if ($idShop === null) {
            $idShop = Context::getContext()->shop->id;
        }

        $cacheId = 'objectmodel_shop_'.$this->def['classname'].'_'.(int) $this->id.'-'.(int) $idShop;
        if (!ObjectModel::$cache_objects || !Cache::isStored($cacheId)) {
            $associated = (bool)Db::readOnly()->getValue('
				SELECT id_shop
				FROM `'.pSQL(_DB_PREFIX_.$this->def['table']).'_shop`
				WHERE `'.$this->def['primary'].'` = '.(int) $this->id.'
				AND id_shop = '.(int) $idShop
            );

            if (!ObjectModel::$cache_objects) {
                return $associated;
            }

            Cache::store($cacheId, $associated);

            return $associated;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * This function associate an item to its context
     *
     * @param int|array $idShops
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function associateTo($idShops)
    {
        if (!$this->id) {
            return false;
        }

        if (!is_array($idShops)) {
            $idShops = [$idShops];
        }

        $data = [];
        foreach ($idShops as $idShop) {
            if (!$this->isAssociatedToShop($idShop)) {
                $data[] = [
                    $this->def['primary'] => (int) $this->id,
                    'id_shop'             => (int) $idShop,
                ];
            }
        }

        if ($data) {
            return Db::getInstance()->insert($this->def['table'].'_shop', $data);
        }

        return true;
    }

    /**
     * Gets the list of associated shop IDs
     *
     * @return array
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function getAssociatedShops()
    {
        if (!Shop::isTableAssociated($this->def['table'])) {
            return [];
        }

        $list = [];
        $sql = 'SELECT id_shop FROM `'._DB_PREFIX_.$this->def['table'].'_shop` WHERE `'.$this->def['primary'].'` = '.(int) $this->id;
        foreach (Db::readOnly()->getArray($sql) as $row) {
            $list[] = $row['id_shop'];
        }

        return $list;
    }

    /**
     * Copies shop association data from object with specified ID.
     *
     * @param int $id
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     *
     * @throws PrestaShopException
     */
    public function duplicateShops($id)
    {
        if (!Shop::isTableAssociated($this->def['table'])) {
            return false;
        }

        $sql = 'SELECT id_shop
				FROM '._DB_PREFIX_.$this->def['table'].'_shop
				WHERE '.$this->def['primary'].' = '.(int) $id;
        if ($results = Db::readOnly()->getArray($sql)) {
            $ids = [];
            foreach ($results as $row) {
                $ids[] = $row['id_shop'];
            }

            return $this->associateTo($ids);
        }

        return false;
    }

    /**
     * Checks if there is more than one entry in associated shop table for current object.
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function hasMultishopEntries()
    {
        if (!Shop::isTableAssociated($this->def['table']) || !Shop::isFeatureActive()) {
            return false;
        }

        return (bool) Db::readOnly()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.$this->def['table'].'_shop` WHERE `'.$this->def['primary'].'` = '.(int) $this->id);
    }

    /**
     * Checks if object is multi-shop object.
     *
     * @return bool
     */
    public function isMultishop()
    {
        return Shop::isTableAssociated($this->def['table']) || !empty($this->def['multilang_shop']);
    }

    /**
     * Checks if a field is a multi-shop field.
     *
     * @param string $field
     *
     * @return bool
     */
    public function isMultiShopField($field)
    {
        return (isset($this->def['fields'][$field]['shop']) && $this->def['fields'][$field]['shop']);
    }

    /**
     * Checks if the object is both multi-language and multi-shop.
     *
     * @return bool
     */
    public function isLangMultishop()
    {
        return !empty($this->def['multilang']) && !empty($this->def['multilang_shop']);
    }

    /**
     * Updates a table and splits the common datas and the shop datas.
     *
     * @param string $className
     * @param array $data
     * @param string $where
     * @param string $specificWhere Only executed for common table
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function updateMultishopTable($className, $data, $where = '', $specificWhere = '')
    {
        $def = ObjectModel::getDefinition($className);
        $updateData = [];
        foreach ($data as $field => $value) {
            if (!isset($def['fields'][$field])) {
                continue;
            }

            if (!empty($def['fields'][$field]['shop'])) {
                if ($value === null && !empty($def['fields'][$field]['allow_null'])) {
                    $updateData[] = "a.$field = NULL";
                    $updateData[] = "{$def['table']}_shop.$field = NULL";
                } else {
                    $updateData[] = "a.$field = '$value'";
                    $updateData[] = "{$def['table']}_shop.$field = '$value'";
                }
            } else {
                if ($value === null && !empty($def['fields'][$field]['allow_null'])) {
                    $updateData[] = "a.$field = NULL";
                } else {
                    $updateData[] = "a.$field = '$value'";
                }
            }
        }

        $sql = 'UPDATE '._DB_PREFIX_.$def['table'].' a
				'.Shop::addSqlAssociation($def['table'], 'a', true, null, true).'
				SET '.implode(', ', $updateData).
                (!empty($where) ? ' WHERE '.$where : '');

        return Db::getInstance()->execute($sql);
    }

    /**
     * Delete images associated with the object
     *
     * @param bool $forceDelete @deprecated
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function deleteImage($forceDelete = false)
    {
        if (!$this->id) {
            return false;
        }

        $candidates = [];
        $types = $this->image_dir
            ? ImageType::getImagesTypes()
            : [];

        // To make sure we get all relevant image files, we need to loop through all supported image extensions
        foreach (ImageManager::getAllowedImageExtensions(true, true) as $imageExtension) {

            // Deleting tmp images
            $ids_shop = Shop::getCompleteListOfShopsID();
            $ids_shop[] = 0; // Making sure that none shop related image are deleted too

            foreach ($ids_shop as $id_shop) {
                $shop_key = $id_shop ? '_'.$id_shop : '';
                $candidates[] = _PS_TMP_IMG_DIR_ . $this->def['table'] . '_' . $this->id . $shop_key . '.' . $imageExtension;
                $candidates[] = _PS_TMP_IMG_DIR_ . $this->def['table'] . '_mini_' . $this->id . $shop_key . '.' . $imageExtension;
                $candidates[] = _PS_TMP_IMG_DIR_ . $this->def['table'] . '_' . $this->id . $shop_key . '_thumb.' . $imageExtension;
            }

            /* Deleting object images and thumbnails (cache) */
            if ($this->image_dir) {
                $candidates[] = $this->image_dir . $this->id . '.' . $imageExtension;
                foreach ($types as $imageType) {
                    $candidates[] = $this->image_dir . $this->id . '-' . stripslashes($imageType['name']) . '.' . $imageExtension;
                    $candidates[] = $this->image_dir . $this->id . '-' . stripslashes($imageType['name']) . '2x.' . $imageExtension;
                }
            }
        }

        $result = true;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $result = unlink($candidate) && $result;
            }
        }

        return $result;
    }

    /**
     * Checks if an object exists in database.
     *
     * @param int $idEntity
     * @param string $table
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function existsInDatabase($idEntity, $table)
    {
        $row = Db::readOnly()->getRow('
			SELECT `id_'.bqSQL($table).'` as id
			FROM `'._DB_PREFIX_.bqSQL($table).'` e
			WHERE e.`id_'.bqSQL($table).'` = '.(int) $idEntity
        );

        return isset($row['id']);
    }

    /**
     * Checks if an object type exists in the database.
     *
     * @param string|null $table Name of table linked to entity
     * @param bool $hasActiveColumn True if the table has an active column
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public static function isCurrentlyUsed($table = null, $hasActiveColumn = false)
    {
        if ($table === null) {
            $table = static::$definition['table'];
        }

        $query = new DbQuery();
        $query->select('`id_'.bqSQL($table).'`');
        $query->from($table);
        if ($hasActiveColumn) {
            $query->where('`active` = 1');
        }

        return (bool) Db::readOnly()->getValue($query);
    }

    /**
     * Fill an object with given data. Data must be an array with this syntax:
     * array(objProperty => value, objProperty2 => value, etc.)
     *
     * @param array $data
     * @param int|null $idLang
     */
    public function hydrate(array $data, $idLang = null)
    {
        $this->id_lang = $idLang;
        if (isset($data[$this->def['primary']])) {
            $this->id = $data[$this->def['primary']];
        }

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Fill an object with given data. Data must be an array with this syntax:
     * array(
     *   array(id_lang => 1, objProperty => value, objProperty2 => value, etc.),
     *   array(id_lang => 2, objProperty => value, objProperty2 => value, etc.),
     * );
     *
     * @param array $data
     */
    public function hydrateMultilang(array $data)
    {
        foreach ($data as $row) {
            if (isset($row[$this->def['primary']])) {
                $this->id = $row[$this->def['primary']];
            }

            foreach ($row as $key => $value) {
                if (property_exists($this, $key)) {
                    if (!empty($this->def['fields'][$key]['lang']) && !empty($row['id_lang'])) {
                        // Multilang
                        if (!is_array($this->{$key})) {
                            $this->{$key} = [];
                        }
                        $this->{$key}[(int) $row['id_lang']] = $value;
                    } else {
                        // Normal
                        $this->{$key} = $value;
                    }
                }
            }
        }
    }

    /**
     * Fill (hydrate) a list of objects in order to get a collection of these objects
     *
     * @param string $class Class of objects to hydrate
     * @param array $datas List of data (multi-dimensional array)
     * @param int|null $idLang
     *
     * @return array
     * @throws PrestaShopException
     */
    public static function hydrateCollection($class, array $datas, $idLang = null)
    {
        if (!class_exists($class)) {
            throw new PrestaShopException("Class '$class' not found");
        }

        $collection = [];
        $rows = [];
        if ($datas) {
            $definition = ObjectModel::getDefinition($class);
            if (!array_key_exists($definition['primary'], $datas[0])) {
                throw new PrestaShopException("Identifier '{$definition['primary']}' not found for class '$class'");
            }

            foreach ($datas as $row) {
                // Get object common properties
                $id = $row[$definition['primary']];
                if (!isset($rows[$id])) {
                    $rows[$id] = $row;
                }

                // Get object lang properties
                if (isset($row['id_lang']) && !$idLang) {
                    foreach ($definition['fields'] as $field => $data) {
                        if (!empty($data['lang'])) {
                            if (!is_array($rows[$id][$field])) {
                                $rows[$id][$field] = [];
                            }
                            $rows[$id][$field][$row['id_lang']] = $row[$field];
                        }
                    }
                }
            }
        }

        // Hydrate objects
        foreach ($rows as $row) {
            /** @var ObjectModel $obj */
            $obj = new $class();
            $obj->hydrate($row, $idLang);
            $collection[] = $obj;
        }

        return $collection;
    }

    /**
     * Returns object definition
     *
     * @param string|ObjectModelCore $class Name of object or object model instance
     * @param string|null $field Name of field if we want the definition of one field only
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getDefinition($class, $field = null)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if ($field === null) {
            $cacheId = 'objectmodel_def_'.$class;
        }

        if ($field !== null || !Cache::isStored($cacheId)) {
            try {
                $reflection = new ReflectionClass($class);

                if (!$reflection->hasProperty('definition')) {
                    throw new PrestaShopException("Class '$class' does not contain object model definition");
                }

                $definition = $reflection->getStaticPropertyValue('definition');
            } catch (ReflectionException $e) {
                throw new PrestaShopException("Failed to resolve object model definition for '$class'", 0, $e);
            }

            $definition['classname'] = $class;

            if (!empty($definition['multilang'])) {
                $definition['associations'][PrestaShopCollection::LANG_ALIAS] = [
                    'type' => static::HAS_MANY,
                    'field' => $definition['primary'],
                    'foreign_field' => $definition['primary'],
                ];
            }

            if ($field) {
                return $definition['fields'][$field] ?? null;
            }

            Cache::store($cacheId, $definition);

            return $definition;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Retrocompatibility for classes without $definition static
     *
     * @deprecated 2.0.0
     */
    protected function setDefinitionRetrocompatibility()
    {
        // Retrocompatibility with $table property ($definition['table'])
        if (isset($this->def['table'])) {
            $this->table = $this->def['table'];
        } else {
            $this->def['table'] = $this->table;
        }

        // Retrocompatibility with $identifier property ($definition['primary'])
        if (isset($this->def['primary'])) {
            $this->identifier = $this->def['primary'];
        } else {
            $this->def['primary'] = $this->identifier;
        }

        // Check multilang retrocompatibility
        if (method_exists($this, 'getTranslationsFieldsChild')) {
            $this->def['multilang'] = true;
        }

        // Retrocompatibility with $fieldsValidate, $fieldsRequired and $fieldsSize properties ($definition['fields'])
        if (isset($this->def['fields'])) {
            foreach ($this->def['fields'] as $field => $data) {
                $isLang = (isset($data['lang']) && $data['lang']);
                if (isset($data['validate'])) {
                    if ($isLang) {
                        $this->fieldsValidateLang[$field] = $data['validate'];
                    } else {
                        $this->fieldsValidate[$field] = $data['validate'];
                    }
                }
                if (isset($data['required']) && $data['required']) {
                    if ($isLang) {
                        $this->fieldsRequiredLang[] = $field;
                    } else {
                        $this->fieldsRequired[] = $field;
                    }
                }
                if (isset($data['size'])) {
                    if ($isLang) {
                        $this->fieldsSizeLang[$field] = $data['size'];
                    } else {
                        $this->fieldsSize[$field] = $data['size'];
                    }
                }
            }
        } else {
            $this->def['fields'] = [];
            foreach ($this->fieldsValidate as $field => $validate) {
                $this->def['fields'][$field]['validate'] = $validate;
            }
            foreach ($this->fieldsRequired as $field) {
                $this->def['fields'][$field]['required'] = true;
            }
            foreach ($this->fieldsSize as $field => $size) {
                $this->def['fields'][$field]['size'] = $size;
            }
            foreach ($this->fieldsValidateLang as $field => $validate) {
                $this->def['fields'][$field]['validate'] = $validate;
                $this->def['fields'][$field]['lang'] = true;
            }
            foreach ($this->fieldsRequiredLang as $field) {
                $this->def['fields'][$field]['required'] = true;
                $this->def['fields'][$field]['lang'] = true;
            }
            foreach ($this->fieldsSizeLang as $field => $size) {
                $this->def['fields'][$field]['size'] = $size;
                $this->def['fields'][$field]['lang'] = true;
            }
        }
    }

    /**
     * Return the field value for the specified language if the field is multilang,
     * else the field value.
     *
     * @param string $fieldName
     * @param int|null $idLang
     *
     * @return mixed
     * @throws PrestaShopException
     */
    public function getFieldByLang($fieldName, $idLang = null)
    {
        $definition = ObjectModel::getDefinition($this);
        // Is field in definition?
        if ($definition && isset($definition['fields'][$fieldName])) {
            $field = $definition['fields'][$fieldName];
            // Is field multilang?
            if (isset($field['lang']) && $field['lang']) {
                if (is_array($this->{$fieldName})) {
                    return $this->{$fieldName}[$idLang ?: Context::getContext()->language->id];
                }
            }

            return $this->{$fieldName};
        } else {
            throw new PrestaShopException('Could not load field from definition.');
        }
    }

    /**
     * Set a list of specific fields to update
     * array(field1 => true, field2 => false,
     * langfield1 => array(1 => true, 2 => false))
     *
     * @param array $fields
     */
    public function setFieldsToUpdate(array $fields)
    {
        $this->update_fields = $fields;
    }

    /**
     * Enables object caching
     *
     * @return void
     */
    public static function enableCache()
    {
        ObjectModel::$cache_objects = true;
    }

    /**
     * Disables object caching
     *
     * @return void
     */
    public static function disableCache()
    {
        ObjectModel::$cache_objects = false;
    }

    /**
     *  Create the database table with its columns. Similar to the createColumn() method.
     *
     * @param string|null $className Class name
     *
     * @return bool Indicates whether the database was successfully added
     *
     * @throws PrestaShopException
     */
    public static function createDatabase($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = static::getDefinition($className);
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'` (';
        $sql .= '`'.$definition['primary'].'` INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,';
        foreach ($definition['fields'] as $fieldName => $field) {
            if ($fieldName === $definition['primary']) {
                continue;
            }
            if (isset($field['lang']) && $field['lang'] || isset($field['shop']) && $field['shop']) {
                continue;
            }

            if (empty($field['db_type'])) {
                switch ($field['type']) {
                    case '1':
                        $field['db_type'] = 'INT(11) UNSIGNED';
                        break;
                    case '2':
                        $field['db_type'] .= 'TINYINT(1)';
                        break;
                    case '3':
                        (isset($field['size']) && $field['size'] > 256)
                            ? $field['db_type'] = 'VARCHAR(256)'
                            : $field['db_type'] = 'VARCHAR(512)';
                        break;
                    case '4':
                        $field['db_type'] = 'DECIMAL(20,6)';
                        break;
                    case '5':
                        $field['db_type'] = 'DATETIME';
                        break;
                    case '6':
                        $field['db_type'] = 'TEXT';
                        break;
                }
            }
            $sql .= '`'.$fieldName.'` '.$field['db_type'];

            if (isset($field['required'])) {
                $sql .= ' NOT NULL';
            }
            if (isset($field['default'])) {
                $sql .= ' DEFAULT \''.$field['default'].'\'';
            }
            $sql .= ',';
        }
        $sql = trim($sql, ',');
        $sql .= ')';

        $conn = Db::getInstance();
        try {
            $success = $conn->execute($sql);
        } catch (PrestaShopDatabaseException $exception) {
            static::dropDatabase($className);

            return false;
        }

        if (isset($definition['multilang']) && $definition['multilang']
            || isset($definition['multilang_shop']) && $definition['multilang_shop']) {
            $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'_lang` (';
            $sql .= '`'.$definition['primary'].'` INT(11) UNSIGNED NOT NULL,';
            foreach ($definition['fields'] as $fieldName => $field) {
                if ($fieldName === $definition['primary'] || !(isset($field['lang']) && $field['lang'])) {
                    continue;
                }
                $sql .= '`'.$fieldName.'` '.$field['db_type'];
                if (isset($field['required'])) {
                    $sql .= ' NOT NULL';
                }
                if (isset($field['default'])) {
                    $sql .= ' DEFAULT \''.$field['default'].'\'';
                }
                $sql .= ',';
            }

            // Lang field
            $sql .= '`id_lang` INT(11) NOT NULL,';

            if (isset($definition['multilang_shop']) && $definition['multilang_shop']) {
                $sql .= '`id_shop` INT(11) NOT NULL,';
            }

            // Primary key
            $sql .= 'PRIMARY KEY (`'.bqSQL($definition['primary']).'`, `id_lang`)';

            $sql .= ')';

            try {
                $success = $conn->execute($sql) && $success;
            } catch (PrestaShopDatabaseException $exception) {
                static::dropDatabase($className);

                return false;
	        }
        }

        if (isset($definition['multishop']) && $definition['multishop']
            || isset($definition['multilang_shop']) && $definition['multilang_shop']) {
            $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'_shop` (';
            $sql .= '`'.$definition['primary'].'` INT(11) UNSIGNED NOT NULL,';
            foreach ($definition['fields'] as $fieldName => $field) {
                if ($fieldName === $definition['primary'] || !(isset($field['shop']) && $field['shop'])) {
                    continue;
                }
                $sql .= '`'.$fieldName.'` '.$field['db_type'];
                if (isset($field['required'])) {
                    $sql .= ' NOT NULL';
                }
                if (isset($field['default'])) {
                    $sql .= ' DEFAULT \''.$field['default'].'\'';
                }
                $sql .= ',';
            }

            // Shop field
            $sql .= '`id_shop` INT(11) NOT NULL,';

            // Primary key
            $sql .= 'PRIMARY KEY (`'.bqSQL($definition['primary']).'`, `id_shop`)';

            $sql .= ')';

            try {
                $success = $conn->execute($sql) && $success;
            } catch (PrestaShopDatabaseException $exception) {
                static::dropDatabase($className);

                return false;
            }
        }

        return $success;
    }

    /**
     * Drop the database for this ObjectModel
     *
     * @param string|null $className Class name
     *
     * @return bool Indicates whether the database was successfully dropped
     *
     * @throws PrestaShopException
     */
    public static function dropDatabase($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = ObjectModel::getDefinition($className);

        $conn = Db::getInstance();
        $success = $conn->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'`');

        if (isset($definition['multilang']) && $definition['multilang']
            || isset($definition['multilang_shop']) && $definition['multilang_shop']) {
            $success = $conn->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'_lang`') && $success;
        }

        if (isset($definition['multishop']) && $definition['multishop']
            || isset($definition['multilang_shop']) && $definition['multilang_shop']) {
            $success = $conn->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'_shop`') && $success;
        }

        return $success;
    }

    /**
     * Get columns in database
     *
     * @param string|null $className Class name
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getDatabaseColumns($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = ObjectModel::getDefinition($className);

        $sql = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=\''._DB_NAME_.'\' AND TABLE_NAME=\''._DB_PREFIX_.pSQL($definition['table']).'\'';

        return Db::readOnly()->getArray($sql);
    }

    /**
     * Add a column in the table relative to the ObjectModel.
     * This method uses the $definition property of the ObjectModel,
     * with some extra properties.
     *
     * Example:
     * 'table'        => 'tablename',
     * 'primary'      => 'id',
     * 'fields'       => array(
     *     'id'     => array('type' => self::TYPE_INT, 'validate' => 'isInt'),
     *     'number' => array(
     *         'type'     => self::TYPE_STRING,
     *         'db_type'  => 'varchar(20)',
     *         'required' => true,
     *         'default'  => '25'
     *     ),
     * ),
     *
     * The primary column is created automatically as INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT. The other columns
     * require an extra parameter, with the type of the column in the database.
     *
     * @param string $name Column name
     * @param array $columnDefinition Column type definition
     * @param string|null $className Class name
     *
     * @return bool Indicates whether the column was successfully created
     *
     * @throws PrestaShopException
     */
    public static function createColumn($name, $columnDefinition, $className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = static::getDefinition($className);
        $sql = 'ALTER TABLE `'._DB_PREFIX_.bqSQL($definition['table']).'`';
        $sql .= ' ADD COLUMN `'.bqSQL($name).'` '.bqSQL($columnDefinition['db_type']);
        if ($name === $definition['primary']) {
            $sql .= ' INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT';
        } else {
            if (isset($columnDefinition['required']) && $columnDefinition['required']) {
                $sql .= ' NOT NULL';
            }
            if (isset($columnDefinition['default'])) {
                $sql .= ' DEFAULT "'.pSQL($columnDefinition['default']).'"';
            }
        }

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     *  Create in the database every column detailed in the $definition property that are
     *  missing in the database.
     *
     * @param string|null $className Class name
     *
     * @return bool Indicates whether the missing columns were successfully created
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     *
     * @todo    : Support multishop and multilang
     */
    public static function createMissingColumns($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $success = true;

        $definition = static::getDefinition($className);
        $columns = static::getDatabaseColumns();
        foreach ($definition['fields'] as $columnName => $columnDefinition) {
            //column exists in database
            $exists = false;
            foreach ($columns as $column) {
                if ($column['COLUMN_NAME'] === $columnName) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $success = static::createColumn($columnName, $columnDefinition) && $success;
            }
        }

        return $success;
    }

}
