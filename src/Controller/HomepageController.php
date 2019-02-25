<?php

namespace App\Controller;


use App\Entity\Attachment;
use App\Entity\AttachmentType;
use App\Entity\Category;
use App\Entity\Part;
use App\Entity\StructuralDBElement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomepageController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    function homepage()
    {
        return $this->render('base.html.twig');
    }
}