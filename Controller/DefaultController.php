<?php

namespace CampaignChain\Activity\XingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('CampaignChainActivityXingBundle:Default:index.html.twig', array('name' => $name));
    }
}
