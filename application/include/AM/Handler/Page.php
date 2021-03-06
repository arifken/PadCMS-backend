<?php
/**
 * @file
 * AM_Handler_Page class definition.
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
 * Page handler
 *
 * @ingroup AM_Handler
 */
class AM_Handler_Page extends AM_Handler_Abstract
{
    /**
     * Creates new page
     * TODO: there is a bug in JS - when page has been created, jumpers are not updated and has wrong id's
     * @param AM_Model_Db_Page $oPageParent
     * @param AM_Model_Db_Template $oTemplate
     * @param string $sConnectionType
     * @param array $aUser
     * @param bool $bBetween
     * @return \AM_Model_Db_Page
     */
    public function addPage(AM_Model_Db_Page $oPageParent, AM_Model_Db_Template $oTemplate, $sConnectionType, $aUser, $bBetween = false)
    {
        $oPageConnectedToParent = null;

        if (empty($aUser)) {
            throw new AM_Handler_Exception('Wrong user was given');
        }

        if (!in_array($sConnectionType, AM_Model_Db_Page::$aLinkTypes)) {
            throw new AM_Handler_Exception('Wrong connection type was given');
        }

        if ($bBetween) {
            //We trying to insert new page between two pages. We have to get both pages
            $oPageConnectedToParent = $oPageParent;
            $oPageParent            = AM_Model_Db_Table_Abstract::factory('page')->findConnectedPage($oPageConnectedToParent, $sConnectionType);
            if (is_null($oPageParent)) {
                throw new AM_Handler_Exception('Can\'t find parent page');
            }

            $oPageParent->setReadOnly(false);
        }

        if (is_null($oPageParent)) {
            throw new AM_Handler_Exception('Wrong parent page was given');
        }

        $oPage = new AM_Model_Db_Page();
        $oPage->title    = $sConnectionType . ' connected to page ' . $oPageParent->id;
        $oPage->template = $oTemplate->id;
        $oPage->revision = $oPageParent->revision;
        $oPage->user     = $aUser['id'];
        $oPage->created  = new Zend_Db_Expr('NOW()');
        $oPage->updated  = new Zend_Db_Expr('NOW()');
        $oPage->setConnectionBit($oPage->reverseLinkType($sConnectionType));
        $oPage->save();

        $oPage->setLinkType($sConnectionType);
        $oPage->setParent($oPageParent);
        $oPage->savePageImposition();

        $oPageParent->setConnectionBit($sConnectionType);
        $oPageParent->save();

        if (!is_null($oPageConnectedToParent)) {
            //Remove old connections
            AM_Model_Db_Table_Abstract::factory('page_imposition')
                    ->deleteBy(array('is_linked_to' => $oPageConnectedToParent->id, 'link_type' => $sConnectionType));

            $oPageConnectedToParent->setLinkType($sConnectionType);
            $oPageConnectedToParent->setParent($oPage);
            $oPageConnectedToParent->savePageImposition();

            $oPage->setConnectionBit($sConnectionType);
            $oPage->save();
        }

        return $oPage;
    }

    /**
     * Get page's branch
     *
     * @param AM_Model_Db_Page $oPage
     * @param string $sLinkType
     * @return array
     */
    public function getBranch(AM_Model_Db_Page $oPage, $sLinkType)
    {
        $aBranch = array();
        $this->_getParentsBranch($oPage, $aBranch, $sLinkType);
        $this->_getChildsBranch($oPage, $aBranch, $sLinkType);

        return $aBranch;
    }

    /**
     * Get branch from parents
     *
     * @param AM_Model_Db_Page $oPage
     * @param array $aBranch
     * @param string $sLinkType
     * @return \AM_Handler_Page
     */
    private function _getParentsBranch(AM_Model_Db_Page $oPage, &$aBranch, $sLinkType)
    {
        $sParentLinkType = $oPage->getLinkType(); //If page has parrent, page connected to parent on oPage::getLinkType side
        if (is_null($oPage->getLinkType())) {
            return $this;
        }

        if ($sLinkType == $oPage->reverseLinkType($sParentLinkType)) {
            $oPageParent = $oPage->getParent();
            $aBranch[] = self::parsePage($oPageParent);
            $this->_getChildsBranch($oPageParent, $aBranch, $sLinkType);
            $this->_getParentsBranch($oPageParent, $aBranch, $sLinkType);
        }

        return $this;
    }

    /**
     * Get branch from childs
     *
     * @param AM_Model_Db_Page $oPage
     * @param array $aBranch
     * @param string $sLinkType
     * @return \AM_Handler_Page
     */
    private function _getChildsBranch(AM_Model_Db_Page $oPage, &$aBranch, $sLinkType)
    {
        $aPageChilds = $oPage->getChilds();

        foreach ($aPageChilds as $oPageChild) {
            if ($sLinkType == $oPageChild->getLinkType()) {
                $aBranch[] = self::parsePage($oPageChild);
                $this->_getChildsBranch($oPageChild, $aBranch, $sLinkType);
                $this->_getParentsBranch($oPageChild, $aBranch, $sLinkType);
            }
        }

        return $this;
    }

    /**
     * Returns an array with pages data for view
     *
     * @param AM_Model_Db_Page $oPage
     * @return array
     */
    public static function parsePage(AM_Model_Db_Page $oPage)
    {
        $aPage = $oPage->toArray();

        $aPage['tpl_title'] = $oPage->getTemplate()->description;

        $aPage['has_left']   = $oPage->hasConnection(AM_Model_Db_Page::LINK_LEFT);
        $aPage['has_right']  = $oPage->hasConnection(AM_Model_Db_Page::LINK_RIGHT);
        $aPage['has_top']    = $oPage->hasConnection(AM_Model_Db_Page::LINK_TOP);
        $aPage['has_bottom'] = $oPage->hasConnection(AM_Model_Db_Page::LINK_BOTTOM);

        //Restrict user to add left and right childs in pages which have bottom or top parent
            $aPage['tpl']['has_left']   = ($oPage->getLinkType() != AM_Model_Db_Page::LINK_RIGHT) && !is_null($oPage->getLinkType())? 0 : $oPage->getTemplate()->has_left_connector;
            $aPage['tpl']['has_right']  = ($oPage->getLinkType() != AM_Model_Db_Page::LINK_RIGHT) && !is_null($oPage->getLinkType())? 0 : $oPage->getTemplate()->has_right_connector;

        $aPage['tpl']['has_top']    = $oPage->getTemplate()->has_top_connector;
        $aPage['tpl']['has_bottom'] = $oPage->getTemplate()->has_bottom_connector;


        $oPageRoot = $oPage->getParent();
        if (!is_null($oPageRoot)) {
            $sLinkType                     = $oPage->reverseLinkType($oPage->getLinkType());
            $aPage[$sLinkType]             = $oPageRoot->id;
            $aPage['jumper_' . $sLinkType] = $oPage->getLinkType(); //The direction of the arrow in the pages tree
        }

        foreach ($oPage->getChilds() as $oPageChild) {
            $sLinkType                     = $oPageChild->getLinkType();
            $aPage[$sLinkType]             = $oPageChild->id;
            $aPage['jumper_' . $sLinkType] = $sLinkType; //The direction of the arrow in the pages tree
        }

        $aPage['link_type'] = $oPage->getLinkType();

        $aPage['thumbnailUri'] = $oPage->getPageBackgroundUri();

        return $aPage;
    }
}