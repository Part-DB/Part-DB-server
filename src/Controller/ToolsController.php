<?php


namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tools")
 * @package App\Controller
 */
class ToolsController extends AbstractController
{

    /**
     * @Route("/reel_calc", name="tools_reel_calculator")
     * @return Response
     */
    public function reelCalculator() : Response
    {
        $this->denyAccessUnlessGranted('@tools.reel_calculator');

        return $this->render("Tools/ReelCalculator/main.html.twig");
    }
}