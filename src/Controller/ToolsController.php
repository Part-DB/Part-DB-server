<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tools")
 */
class ToolsController extends AbstractController
{
    /**
     * @Route("/reel_calc", name="tools_reel_calculator")
     */
    public function reelCalculator(): Response
    {
        $this->denyAccessUnlessGranted('@tools.reel_calculator');

        return $this->render('Tools/ReelCalculator/main.html.twig');
    }
}
