<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CampaignChain\Activity\XingBundle\Controller;

use CampaignChain\CoreBundle\Controller\Module\AbstractActivityHandler;
use Symfony\Component\Form\Form;
use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\CoreBundle\Entity\Campaign;
use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\Entity\Operation;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\HttpFoundation\Session\Session;

use CampaignChain\Operation\XingBundle\Entity\XingMessage;
use CampaignChain\Operation\XingBundle\EntityService\XingMessage as XingMessageService;
use CampaignChain\Operation\XingBundle\Job\XingMessage as XingMessageJob;

/**
 * Class XingHandler
 * @package CampaignChain\Activity\XingBundle\Controller\Module
 */
class XingHandler extends AbstractActivityHandler
{
    protected $em;
    protected $session;
    protected $templating;
    protected $contentService;
    protected $job;
    private $message;

    public function __construct(
        EntityManager $em,
        Session $session,
        TwigEngine $templating,
        XingMessageService $contentService,
        XingMessageJob $job
    )
    {
        $this->em = $em;
        $this->session = $session;
        $this->templating = $templating;
        $this->contentService = $contentService;
        $this->job = $job;
    }

    /**
     * When a new Activity is being created, this handler method will be called
     * to retrieve a new Content object for the Activity.
     *
     * Called in these views:
     * - new
     *
     * @param Location $location
     * @param Campaign $campaign
     * @return null
     */
    public function createContent(Location $location = null, Campaign $campaign = null)
    {
        return null;
    }

    /**
     * Overwrite this method to return an existing Activity Content object which
     * would be displayed in a view.
     *
     * Called in these views:
     * - edit
     * - editModal
     * - read
     *
     * @param Location $location
     * @param Operation $operation
     * @return null
     */
    public function getContent(Location $location, Operation $operation)
    {
        return $this->contentService->getMessageByOperation($operation);
    }

    /**
     * Implement this method to change the data of an Activity as per the form
     * data that has been posted in a view.
     *
     * Called in these views:
     * - new
     *
     * @param Activity $activity
     * @param $data Form submit data of the Activity.
     * @return Activity
     */
    public function processActivity(Activity $activity, $data)
    {
        return $activity;
    }

    /**
     * Modifies the Location of the Activity.
     *
     * Called in these views:
     * - new
     *
     * @param Location $location The Activity's Location.
     * @return Location
     */
    public function processActivityLocation(Location $location)
    {
        return $location;
    }

    /**
     * After a new Activity was created, this method makes it possible to alter
     * the data of the Content's Location (not the Activity's Location!) as per
     * the data provided for the Content.
     *
     * Called in these views:
     * - new
     *
     * @param Location $location Location of the Content.
     * @param $data Form submit data of the Content.
     * @return Location
     */
    public function processContentLocation(Location $location, $data)
    {
        return $location;
    }

    /**
     * Create or modify the Content object from the form data.
     *
     * Called in these views:
     * - new
     * - editApi
     *
     * @param Operation $operation
     * @param $data Form submit data of the Content.
     * @return mixed
     */
    public function processContent(Operation $operation, $data)
    {
        try {
            if(is_array($data)) {
                $message = $this->contentService->getMessageByOperation($operation);
                $message->setMessage($data['message']);
            } else {
                $message = $data;            
            }
        } catch (\Exception $e){
            // message has not been created yet, so do it from the form data.
            $message = $data;
        }
        return $message;
    }

    /**
    * Define custom template rendering options for the new view in this method
    * as an array. Here's a sample of such an array:
    *
    * array(
    *     'template' => 'foo_template::edit.html.twig',
    *     'vars' => array(
    *         'foo1' => $bar1,
    *         'foo2' => $bar2
    *         )
    *     );
    *
    * Called in these views:
    * - new
    *
    * @param Operation $operation
    * @return null
    */
    public function getNewRenderOptions(Operation $operation = null)
    {
        return null;
    }

    /**
     * Overwrite this method to define how the Content is supposed to be
     * displayed.
     *
     * Called in these views:
     * - read
     *
     * @param Operation $operation
     * @return mixed
     */
    public function readAction(Operation $operation)
    {
        $message = $this->contentService->getMessageByOperation($operation);
        return $this->templating->renderResponse(
            'CampaignChainOperationXingBundle::read_message.html.twig',
            array(
                'page_title' => $operation->getActivity()->getName(),
                'operation' => $operation,
                'location' => $operation->getActivity()->getLocation(),
                'activity' => $operation->getActivity(),
                'message' => $message,
                'show_date' => true,
            ));
    }

    /**
     * The Activity controller calls this method after the form was submitted
     * and the new activity was persisted.
     *
     * @param Activity $activity
     * @param $data
     */
    public function postFormSubmitNewEvent(Activity $activity, $data)
    {
    }

    /**
     * This event is being called after the new Activity and its related
     * content was persisted.
     *
     * Called in these views:
     * - new
     *
     * @param Operation $operation
     * @param Form $form
     * @param $content The Activity's content object.
     * @return null
     */
    public function postPersistNewEvent(Operation $operation, Form $form, $content = null)
    {
        $this->publishNow($operation, $form, $content);
        $this->em->persist($content);
        $this->em->flush();
    }

    /**
     * This event is being called before the edit form data has been submitted.
     *
     * Called in these views:
     * - edit
     *
     * @param Operation $operation
     * @return null
     */
    public function preFormSubmitEditEvent(Operation $operation)
    {
        return null;
    }

    /**
     * This event is being called after the edited Activity and its related
     * content was persisted.
     *
     * Called in these views:
     * - edit
     *
     * @param Operation $operation
     * @param Form $form
     * @param $content The Activity's content object.
     * @return null
     */
    public function postPersistEditEvent(Operation $operation, Form $form, $content = null)
    {
        $this->publishNow($operation, $form, $content);
    }

    /**
     * Define custom template rendering options for the edit view in this method
     * as an array. Here's a sample of such an array:
     *
     * array(
     *     'template' => 'foo_template::edit.html.twig',
     *     'vars' => array(
     *         'foo1' => $bar1,
     *         'foo2' => $bar2
     *         )
     *     );
     *
     * Called in these views:
     * - edit
     *
     * @param Operation $operation
     * @return null
     */
    public function getEditRenderOptions(Operation $operation)
    {
        return null;
    }

    /**
     * This event is being called before the editModal form data has been
     * submitted.
     *
     * Called in these views:
     * - editModal
     *
     * @param Operation $operation
     * @return null
     */
    public function preFormSubmitEditModalEvent(Operation $operation)
    {
        return null;
    }

    /**
     * Define custom template rendering options for editModal view as array.
     *
     * Called in these views:
     * - editModal
     *
     * @see AbstractActivityHandler::getEditRenderOptions()
     * @param Operation $operation
     * @return null
     */
    public function getEditModalRenderOptions(Operation $operation)
    {
        return null;
    }

    /**
     * Let's a handler implementation define whether the Content should be
     * displayed or processed in a specific view or not.
     *
     * Called in these views:
     * - new
     * - edit
     * - editModal
     * - editApi
     *
     * @param $view
     * @return bool
     */
    public function hasContent($view)
    {
        return true;
    }
    
    private function publishNow(Operation $operation, Form $form)
    {
       if ($form->get('campaignchain_hook_campaignchain_due')->has('execution_choice') && $form->get('campaignchain_hook_campaignchain_due')->get('execution_choice')->getData() == 'now') {
            $this->job->execute($operation->getId());
            $content = $this->contentService->getMessageByOperation($operation);
            $this->session->getFlashBag()->add(
                'success',
                'The message was published. <a href="'.$content->getUrl().'">View it on XING</a>.'
            );
            return true;
        }
        return false;    
    }
}