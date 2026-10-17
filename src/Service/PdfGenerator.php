<?php

namespace App\Service;

use Twig\Environment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Response;

class PdfGenerator
{
    private $twig;
    private $filesystem;

    public function __construct(Environment $twig, Filesystem $filesystem)
    {
        $this->twig = $twig;
        $this->filesystem = $filesystem;
    }

    public function generate($path, $fileName, $template, $object)
    {
        $pdfOptions = new Options();
        $pdfOptions->set(['enable_remote' => true, 'chroot'  => '/public/']);
        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($this->twig->render($template, $object));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        try {
            $this->filesystem->mkdir(Path::normalize($path));
            file_put_contents($path.'/ '.$fileName, $dompdf->output());
        } catch (IOExceptionInterface $exception) {
            dump("An error occurred while creating your directory at ".$exception->getPath());
        }
    }

    public function stream($template, $object): Response
    {
        $pdfOptions = new Options();
        $pdfOptions->setIsRemoteEnabled(true);
        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($this->twig->render($template, $object));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = $dompdf->output();

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="'.uniqid().'.pdf"');

        return $response;
    }
    
        
    public function imageToBase64($path) 
    {
        // Sécurité : vérifie l'existence physique du fichier avant la lecture
        if (!$path || !file_exists($path) || !is_file($path)) {
            return '';
        }

        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}
