<?php
/**
 * @file
 * AM_Model_Db_Element_Data_Abstract class definition.
 *
 * LICENSE
 *
 * This software is governed by the CeCILL-C  license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL-C
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL-C license and that you accept its terms.
 *
 * @author Copyright (c) PadCMS (http://www.padcms.net)
 * @version $DOXY_VERSION
 */

/**
 * This class encapsulates logic of work with element's resources - files, strings, etc.
 * @todo Rename
 * @ingroup AM_Model
 */
abstract class AM_Model_Db_Element_Data_Abstract implements AM_Model_Db_Element_Data_Interface
{
    const TYPE = 'element';

    /** @var AM_Model_Db_Element **/
    protected $_oElement = null; /**< @type AM_Model_Db_Element */
    /** @var array **/
    protected $_aData = array(); /**< @type array */
    /** @var string **/
    protected $_sTempPath = null; /**< @type string */

    /**
     * @param AM_Model_Db_Element $oElement
     * @param array $aData
     */
    public final function __construct(AM_Model_Db_Element $oElement, $aData = null)
    {
        $this->_oElement = $oElement;
        if (is_null($aData)) {
            $this->fetchData();
        } else {
            $this->setData($aData);
        }

        $this->_init();
    }

    /**
     * @see AM_Model_Db_Element_Data_Interface::getData()
     * @return array
     */
    public function getData()
    {
        return $this->_aData;
    }

    /**
     * Get all element's data
     * @return AM_Model_Db_Element_Data_Abstract
     */
    public function fetchData()
    {
        $oElementDataSet = AM_Model_Db_Table_Abstract::factory('element_data')
                ->findAllBy(array('id_element' => $this->getElement()->id));

        foreach ($oElementDataSet as $oRow) {
            $this->_aData[$oRow->key_name] = $oRow;
        }

        return $this;
    }

    /**
     * Remove all elements data and cretes new from array
     * @param array $aData
     * @see AM_Model_Db_Element_Data_Interface::setData()
     */
    public function setData($aData)
    {
        $this->delete();

        foreach ($aData as $sKey => $mValue) {
            $oElementDataRow               = new AM_Model_Db_ElementData();
            $oElementDataRow->id_element   = $this->getElement()->id;
            $oElementDataRow->key_name     = $sKey;
            $oElementDataRow->value        = $mValue;
            $this->_aData[$sKey] = $oElementDataRow;
        }

        return $this;
    }

    /**
     * Prepare data
     */
    protected function _init()
    {}

    /**
     * Get element instance
     * @return AM_Model_Db_Element
     */
    public function getElement()
    {
        return $this->_oElement;
    }

    /**
     * Add key-value data to the element
     * @param string $sKey
     * @param mixed $mValue
     * @param boolean $bHasToSave Save element data or not (need for uploading)
     * @return AM_Model_Db_Element_Data_Abstract
     * @throws AM_Model_Db_Element_Data_Exception
     */
    public function addKeyValue($sKey, $mValue, $bHasToSave = true)
    {
        $sKey = (string) $sKey;

        if (empty($sKey)) {
            throw new AM_Model_Db_Element_Data_Exception('Trying to add value with empty key');
        }

        //Handle value by user defined callback
        $sCallbackName = '_add' . ucfirst(Zend_Filter::filterStatic($sKey, 'Word_UnderscoreToCamelCase'));
        if (method_exists($this, $sCallbackName)) {
            $mValue = $this->$sCallbackName($mValue);
        }

        $oElementDataRow = AM_Model_Db_Table_Abstract::factory('element_data')
                ->findOneBy(array('id_element' => $this->getElement()->id, 'key_name' => $sKey));

        if (is_null($oElementDataRow)) {
            $oElementDataRow = new AM_Model_Db_ElementData();
        }

        $oElementDataRow->id_element   = $this->getElement()->id;
        $oElementDataRow->key_name     = $sKey;
        $oElementDataRow->value        = $mValue;
        if ($bHasToSave) {
            $oElementDataRow->save();
        }

        $this->getElement()->updated = new Zend_Db_Expr('NOW()');
        $this->getElement()->save();

        $this->_aData[$sKey] = $oElementDataRow;

        return $this;
    }

    /**
     * @see AM_Model_Db_Element_Data_Interface::copy()
     */
    public function copy()
    {
        $this->_preCopy();

        $aData = $this->getData();
        foreach ($aData as $oElementDataRow) {
            /* @var $oElementDataRow AM_Model_Db_ElementData */
            $aModifiedFields = array('id_element' => $this->getElement()->id);
            $oElementDataRow->copy($aModifiedFields);
        }

        $this->_postCopy();
    }

    /**
     * Pre copy operations
     */
    protected function _preCopy()
    { }

    /**
     * Post copy operations
     */
    protected function _postCopy()
    { }

    /**
     * @see AM_Model_Db_Element_Data_Interface::save()
     */
    public final function save()
    {
        $this->_preSave();

        $aData = $this->getData();
        foreach ($aData as $oElementDataRow) {
            /* @var $oElementDataRow AM_Model_Db_ElementData */
            $oElementDataRow->save();
        }

        $this->_postSave();

        return $this;
    }

    /**
     * Pre save operations
     */
    protected function _preSave()
    { }

    /**
     * Post save operations
     */
    protected function _postSave()
    { }

    /**
     * Get value of extra data by key
     *
     * @param string $sKey
     * @return string | false
     */
    public final function getDataValue($sKey, $mDefaultValue = false)
    {
        $aData = $this->getData();

        if (!array_key_exists($sKey, $aData)) {
            return $mDefaultValue;
        }

        $oElementDataRow = $aData[$sKey];

        return $oElementDataRow->value;
    }

    /**
     * Get data value for export by given key
     *
     * @param string $sKey
     * @return mixed
     */
    public function getDataValueForExport($sKey)
    {
        $sGetterMethodName = '_getExport' . ucfirst(Zend_Filter::filterStatic($sKey, 'Word_UnderscoreToCamelCase'));
        if (method_exists($this, $sGetterMethodName)) {
            $mValue = $this->$sGetterMethodName();
        } else {
            $mValue = $this->getDataValue($sKey);
        }

        return $mValue;
    }

    /**
     * Get row of extra data by key
     * @param string $sKey
     * @return AM_Model_Db_ElementData | false
     */
    public final function getDataRow($sKey)
    {
        $aData = $this->getData();

        if (!array_key_exists($sKey, $aData)) {
            return false;
        }

        $oElementDataRow = $aData[$sKey];

        return $oElementDataRow;
    }

    /**
     * Set temp path for uploaded files
     * @param string $sTempPath
     * @return AM_Model_Db_Element_Data_Abstract
     */
    public function setTempPath($sTempPath)
    {
        if (!AM_Tools_Standard::getInstance()->is_dir($sTempPath)){
            throw new AM_Model_Db_Element_Data_Exception("Wrong temp path given");
        }

        $this->_sTempPath = $sTempPath;

        return $this;
    }

    /**
     * Get temp path
     * @return string
     */
    public function getTempPath()
    {
        return $this->_sTempPath;
    }

    /**
     * Delete resources data
     *
     * @param string|null $sKey Have to delete data by $key or all data
     * @param boolean $bTriggerEnable Do we have to run trigger
     * @return AM_Model_Db_Element_Data_Abstract
     */
    public final function delete($sKey = null, $bTriggerEnable = true)
    {
        $aCriteria = array('id_element' => $this->getElement()->id);
        if (!is_null($sKey)) {
            $aCriteria['key_name'] = $sKey;
        }
        $iRowsDeleted = AM_Model_Db_Table_Abstract::factory('element_data')
                ->deleteBy($aCriteria);

        if ($iRowsDeleted && $bTriggerEnable) {
            $this->_postDelete($sKey);
        }

        return $this;
    }

    /**
     * Allows post-delete logic to be applied to element data.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _postDelete($sKey = null)
    { }

    /**
     * Create new element or return exists
     *
     * @param AM_Model_Db_Page $oPage
     * @param AM_Model_Db_Field $oField
     * @return AM_Model_Db_Element
     */
    public static function getElementForPageAndField(AM_Model_Db_Page $oPage, AM_Model_Db_Field $oField)
    {
        $oElement = AM_Model_Db_Table_Abstract::factory('element')
                ->findOneBy(array('page' => $oPage->id, 'field' => $oField->id));

        if (!is_null($oElement)) {
            return $oElement;
        }

        $oElement = new AM_Model_Db_Element();
        $oElement->setPage($oPage);
        $oElement->page  = $oPage->id;
        $oElement->field = $oField->id;
        $oElement->save();

        return $oElement;
    }

    /**
     * Retruns name of the resource's preset name to resize
     *
     * @return string
     */
    public function getThumbnailPresetName()
    {
        return self::TYPE . '-' . $this->getElement()->getPage()->getOrientation();
    }
}