<?php

namespace App\Controller;


use App\Entity\Attachment;
use App\Entity\AttachmentType;
use App\Entity\Category;
use App\Entity\StructuralDBElement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomepageController extends AbstractController
{
    /**
     * @Route("/")
     */
    function homepage()
    {
        $repo = $this->getDoctrine()->getRepository(Category::class);

        /** @var StructuralDBElement  $attachment */
        $attachment = $repo->find(1);

        dump($attachment, $attachment->getSubelements(false)->toArray());
        $response = "";
        return $this->render('base.html.twig');
    }
}