<?php

namespace BackBuilder\Services\Local;

use BackBuilder\BBApplication,
    BackBuilder\Services\Local\IServiceLocal,
    BackBuilder\Services\Content\ContentRender,
    BackBuilder\Services\Content\Category,
    BackBuilder\Services\Exception\ServicesException;

class ClassContent extends AbstractServiceLocal {

    private $_frontData = null;
    private $_processedContent = array();

    /**
     * Return the serialized form of a page
     * @exposed(secured=true)
     * 
     */
    public function find($classname, $uid) {
        $em = $this->bbapp->getEntityManager();
        $content = $em->getRepository('\BackBuilder\ClassContent\\' . $classname)->find($uid);

        if (NULL === $content)
            throw new ServicesException(sprintf('Unable to find content for `%s` uid', $uid));

        $content = json_decode($content->serialize());

        return $content;
    }

    private function prepareContentData($initial_content, $datas, $accept, $isParentAContentSet = false, $persist = True) {
        //var_dump('prepareContentData');
        $result = array();
        $em = $this->bbapp->getEntityManager();

        //print_r($accept);
        //print_r($datas);
        if (is_array($datas) && count($datas)) {
            foreach ($datas as $key => $contentInfo) {
                //var_dump($key);
                if ($accept && is_array($accept) && count($accept) && !array_key_exists($key, $accept))
                    continue;
                $createDraft = true;
                if ($isParentAContentSet) {
                    $contentInfo = (object) $contentInfo;
                    $contentType = 'BackBuilder\ClassContent\\' . $contentInfo->nodeType;
                    $contentUid = $contentInfo->uid;
                } else {
                    $contentType = (is_array($accept[$key])) ? $accept[$key][0] : $accept[$key];
                    if (0 !== strpos($contentType, 'BackBuilder\ClassContent\\')) {
                        $contentType = 'BackBuilder\ClassContent\\'.$contentType;
                    }
                    $contentUid = $contentInfo;
                }
                if (array_key_exists($contentUid, $this->_frontData)) {
                    $childContent = $this->_frontData[$contentUid];
                    $content = $this->processContent($childContent, $persist);
                    $result[$key] = $content;
                } elseif (null !== $exists = $em->find($contentType, $contentUid)) {
                    $result[$key] = $exists;
                } else {
                    $content = ( $initial_content instanceof \BackBuilder\ClassContent\ContentSet ) ? $initial_content->item($key) : $initial_content->$key;
                    if (null !== $content) $result[$key] = $content;
                }
            }
        }

        return $result;
    }

    /**
     * @exposed(secured=true)
     */
    public function update($data = array()) {
        if (!is_array($data))
            throw new ServicesException("ClassContent.update data can't be empty");
        $this->_frontData = $data;
        $em = $this->bbapp->getEntityManager();
        foreach ($data as $srzContent) {
            $this->processContent($srzContent);
        }
        $em->flush();
    }

    /**
     * @exposed(secured=true)
     */
    public function updateContentRender($renderType, $srzContent = null, $page_uid = null) {
        if (is_null($srzContent))
            throw new ServicesException("ClassContent.update data can't be null");
        $em = $this->bbapp->getEntityManager();
        $srzContent = (object) $srzContent;
        if (FALSE === array_key_exists('uid', $srzContent))
            throw new ServicesException('An uid must be provided');
        $content = $this->bbapp->getEntityManager()->find('BackBuilder\ClassContent\\' . $srzContent->type, $srzContent->uid);
        if (NULL === $content) {
            $classname = 'BackBuilder\ClassContent\\' . $srzContent->type;
            $content = new $classname($srzContent->uid);
        }
        $srzContent->data = null;
        if (NULL !== $draft = $em->getRepository('BackBuilder\ClassContent\Revision')->getDraft($content, $this->bbapp->getBBUserToken(), true)) {
            $content->setDraft($draft);
        }
        $content = $content->unserialize($srzContent);
        if (NULL !== $page_uid && (NULL !== $page = $em->find('BackBuilder\NestedNode\Page', $page_uid)))
            $this->bbapp->getRenderer()->setCurrentPage($page);

        //$cRender = new ContentRender($content["type"], $this->bbapp, null, $renderType, $content["uid"]);
        $result = new \stdClass();
        $result->render = $this->bbapp->getRenderer()->render($content, $renderType);
        return $result;
    }

    /* ne faire qu'un seul traitement */

    private function processContent($srzContent = null, $persist = TRUE) {
        $em = $this->bbapp->getEntityManager();
        if (is_null($srzContent))
            throw new ServicesException("ClassContent.processData data can't be null");

        $srzContent = (object) $srzContent;

        if (FALSE === array_key_exists('uid', $srzContent))
            throw new ServicesException('An uid has to be provided');
        if (array_key_exists($srzContent->uid, $this->_processedContent)) {
            return $this->_processedContent[$srzContent->uid];
        }

        if (0 !== strpos($srzContent->type, 'BackBuilder\ClassContent\\')) {
            $srzContent->type = 'BackBuilder\ClassContent\\'.$srzContent->type;
        }
        //if (!$srzContent->isAContentSet) {
        $content = $this->bbapp->getEntityManager()->find($srzContent->type, $srzContent->uid);

        if (NULL === $content) {
            $classname = $srzContent->type;
            $content = new $classname($srzContent->uid);
            $em->persist($content);
        }

        //Find a draft for content if exists
        if (NULL !== $draft = $em->getRepository('BackBuilder\ClassContent\Revision')->getDraft($content, $this->bbapp->getBBUserToken(), true)) {
            $content->setDraft($draft);
        }

        if (!is_null($srzContent->data)) {
            $srzContent->data = $this->prepareContentData($content, $srzContent->data, $srzContent->accept, $srzContent->isAContentSet, $persist);
        }
        $result = $content->unserialize($srzContent);
        $this->_processedContent[$srzContent->uid] = $result; //notify that content is already processed

        return $result;
    }

    /**
     * @exposed(secured=true)
     */
    public function getContentsData($renderType, $contents = array(), $page_uid = null) {
        $result = array();
        if (is_array($contents)) {
            $receiver = NULL;
            $em = $this->bbapp->getEntityManager();
            
            if (NULL !== $page_uid && (NULL !== $page = $em->find('BackBuilder\NestedNode\Page', $page_uid)))
                $this->bbapp->getRenderer()->setCurrentPage($page);
            
            foreach ($contents as $content) {
                $content = (object) $content;
                $cRender = new ContentRender($content->type, $this->bbapp, null, $renderType, $content->uid);
                if ($cRender) {
                    $classname = '\BackBuilder\ClassContent\\' . $content->type;
                    if (NULL === $nwContent = $em->find($classname, $content->uid)) {
                        $nwContent = new $classname();
                    }
                    /* handle param modification */
                    if (isset($content->serializedContent)) {
                        $nwContent = $nwContent->unserialize((object) $content->serializedContent); // @fixme use updateContentRender
                    }

                    /* moved content with old param */
                    $oContent = $cRender->__toStdObject();
                    $oContent->render = $this->bbapp->getRenderer()->render($nwContent, $renderType);
                    $oContent->serialized = json_decode($nwContent->serialize());
                    $result[] = $oContent;
                }
            }
        }

        return $result;
    }

    /**
     * @exposed(secured=true)
     */
    public function getContentParameters($nodeInfos = array()) {
        $contentType = (is_array($nodeInfos) && array_key_exists("type", $nodeInfos)) ? $nodeInfos["type"] : null;
        $contentUid = (is_array($nodeInfos) && array_key_exists("uid", $nodeInfos)) ? $nodeInfos["uid"] : null;
        if (is_null($contentType) || is_null($contentUid))
            throw new \Exception("params content.type and content.uid can't be null");
        $contentParams = array();
        $contentTypeClass = "BackBuilder\ClassContent\\" . $contentType;

        $em = $this->bbapp->getEntityManager();
        if (NULL === $contentNode = $em->find($contentTypeClass, $contentUid)) {
            $contentNode = new $contentTypeClass($contentUid);
        }

        $default = $contentNode->getDefaultParameters();

        // Find a draft if exists
        if (NULL !== $draft = $em->getRepository('BackBuilder\ClassContent\Revision')->getDraft($contentNode, $this->bbapp->getBBUserToken())) {
            $contentNode->setDraft($draft);
        }
        $contentParams = $contentNode->getParam();
        
        // TO-DO : peut-être à déplacer
        if (is_array($contentParams) && true === array_key_exists('selector', $contentParams) 
                && true === array_key_exists('array', $contentParams['selector'])
                && true === array_key_exists('parentnode', $contentParams['selector']['array'])
                && true === is_array($contentParams['selector']['array']['parentnode'])
                && 0 < count($contentParams['selector']['array']['parentnode'])) {
            $parentnodeTitle = array();
            foreach($contentParams['selector']['array']['parentnode'] as $page_uid) {
                if (NULL !== $page = $em->find('BackBuilder\NestedNode\Page', $page_uid)) {
                    $parentnodeTitle[] = $page->getTitle();
                } else {
                    $parentnodeTitle[] = '';
                }
            }
            $contentParams['selector']['array']['parentnodeTitle'] = $parentnodeTitle;
        }
        
        unset($contentParams["indexation"]);
        return $contentParams;
    }

    public function getContentsByCategory($name = "tous") {

        $contents = array();
        if ($name == "tous") {
            $categoryList = Category::getCategories($this->bbapp);
            //var_dump($categoryList); die();
            foreach ($categoryList as $cat) {
                $cat->setBBapp($this->bbapp);
                foreach ($cat->getContents() as $content)
                    $contents[] = $content->__toStdObject();
            }
        } else {
            $category = new Category($name, $this->bbapp);
            foreach ($category->getContents() as $content)
                $contents[] = $content->__toStdObject();
        }
        return $contents;
    }

    /**
     * @exposed(secured=true)
     */
    public function unlinkColToParent($pageId = null, $contentSetId = null) {
        $pageId = (!is_null($pageId)) ? $pageId : false;
        $contentSetId = (!is_null($contentSetId)) ? $contentSetId : false;
        $result = false;
        if (!$pageId || !$contentSetId) {
            throw new \BackBuilder\Exception\BBException(" a ContentSetId and a PageId must be provided");
        }

        $em = $this->bbapp->getEntityManager();
        $currentPage = $em->find("BackBuilder\NestedNode\\Page", $pageId);
        $contentSetToReplace = $em->find("BackBuilder\ClassContent\ContentSet", $contentSetId);

        if (is_null($contentSetToReplace) || is_null($currentPage)) {
            throw new \BackBuilder\Exception\BBException(" a ContentSet and a Page must be provided");
        }

        /* current page main contentSet will be modified a draft should be created */
        $pageRootContentSet = $currentPage->getContentSet();
        if (NULL !== $draft = $em->getRepository('BackBuilder\ClassContent\Revision')->getDraft($pageRootContentSet, $this->bbapp->getBBUserToken(), true)) {
          $pageRootContentSet->setDraft($draft);
        }

        /* create a draft for the new content */
        $newEmptyContentSet = $contentSetToReplace->createClone();
        $em->persist($newEmptyContentSet);

        //$newEmptyContentSet = new \BackBuilder\ClassContent\ContentSet();
            
        if (NULL !== $draft = $em->getRepository('BackBuilder\ClassContent\Revision')->getDraft($newEmptyContentSet, $this->bbapp->getBBUserToken(), true)) {
            $newEmptyContentSet->setDraft($draft);
        }
        $newEmptyContentSet->clear();
        $em->flush(); 

        /* unlink and update */
        $replace = $em->getRepository('BackBuilder\NestedNode\Page')->replaceRootContentSet($currentPage, $contentSetToReplace, $newEmptyContentSet);
        if ($replace) {
            $em->getRepository("BackBuilder\ClassContent\ContentSet")->updateRootContentSetByPage($currentPage, $contentSetToReplace, $newEmptyContentSet, $this->bbapp->getBBUserToken());
            
            $em->persist($pageRootContentSet);
            $em->flush();

            //$em->persist($newEmptyContentSet);
            /* render the new contentSet */
            $render = $this->bbapp->getRenderer()->render($newEmptyContentSet, null);
            $result = array("render" => $render);
        }
        else{
              throw new \BackBuilder\Exception\BBException("Error while unlinking zone!");
        }

        return $result;
    }

    /**
     * @exposed(secured=true)
     */
    public function linkColToParent($pageId = null, $contentSetId = null) {
        /* Refaire le lien entre la colonne parent */
        $em = $this->bbapp->getEntityManager();
        $pageId = (!is_null($pageId)) ? $pageId : false;
        $result = false;
        
        $contentSetId = (!is_null($contentSetId)) ? $contentSetId : false;

        $contentSetToReplace = $em->find("BackBuilder\ClassContent\\ContentSet", $contentSetId);
        $currentPage = $em->find("BackBuilder\NestedNode\\Page", $pageId);
        
        if (is_null($contentSetToReplace) || is_null($currentPage)) {
            throw new \BackBuilder\Exception\BBException(" a ContentSet and a Page must be provided");
        }
        /* draft for page's maicontainer */
        $pageRootContentSet = $currentPage->getContentSet();
        if (NULL !== $draft = $em->getRepository('BackBuilder\ClassContent\Revision')->getDraft($pageRootContentSet, $this->bbapp->getBBUserToken(), true)) {
          $pageRootContentSet->setDraft($draft);
        }
        
        $parentZoneAtSamePosition = $currentPage->getParentZoneAtSamePositionIfExists($contentSetToReplace);
        if (!$parentZoneAtSamePosition || is_null($parentZoneAtSamePosition))
            return false;
        

        /* draft for parentSimilaireZone */
        if (NULL !== $draft = $em->getRepository('BackBuilder\ClassContent\Revision')->getDraft($parentZoneAtSamePosition, $this->bbapp->getBBUserToken(), true)) {
            $parentZoneAtSamePosition->setDraft($draft);
        }
        
        /* replace page's zone here */
        $replace = $currentPage->replaceRootContentSet($contentSetToReplace, $parentZoneAtSamePosition, false);
        if ($replace) {
            $em->getRepository("BackBuilder\ClassContent\ContentSet")->updateRootContentSetByPage($currentPage, $contentSetToReplace, $parentZoneAtSamePosition, $this->bbapp->getBBUserToken());
            $em->persist($pageRootContentSet);
            $em->flush();
            $render = $this->bbapp->getRenderer()->render($parentZoneAtSamePosition, null);
            $result = array("render" => $render, "newContentUid" => $parentZoneAtSamePosition->getUid());
        }else{
            throw new \BackBuilder\Exception\BBException("Error while linking zone!");
        }

        return $result;


        /* flush
         * $em->flush();
         */
    }

    /**
     * @exposed(secured=true)
     */
    public function getPageLinkedZones($pageId = null) {
        $result = array("mainZones" => null, "linkedZones" => array());
        $pageId = (!is_null($pageId)) ? $pageId : false;
        if (!$pageId)
            return $result;
        $em = $this->bbapp->getEntityManager();
        $currentPage = $em->find("BackBuilder\NestedNode\\Page", $pageId);
        $mainZones = null;
        if (!is_null($currentPage)) {
            $mainZones = $currentPage->getPageMainZones();
            $linkedZones = array_keys($currentPage->getInheritedZones());
            $result["linkedZones"] = $linkedZones;
        }
        if ($mainZones) {
            $result["mainZones"] = array_keys($mainZones);
        }
        return $result;
    }

}