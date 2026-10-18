<?php

namespace App\Tests\Integration\Security;

use App\Entity\Candidature;
use App\Entity\User;
use App\Form\CandidatureType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DocumentSecurityTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->formFactory = static::getContainer()->get('form.factory');
    }

    private function createDummyFile(string $filename, string $mimeType, int $sizeInBytes, string $header = ''): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'doc_sec_test_');
        $fp = fopen($tempPath, 'wb');
        if ($header !== '') {
            fwrite($fp, $header);
            $written = strlen($header);
        } else {
            $written = 0;
        }
        $remaining = max(0, $sizeInBytes - $written);
        if ($remaining > 0) {
            fseek($fp, $sizeInBytes - 1);
            fwrite($fp, '0');
        }
        fclose($fp);

        return new UploadedFile(
            $tempPath,
            $filename,
            $mimeType,
            null,
            true
        );
    }

    public function testOversizedFileIsRejected(): void
    {
        // 3 MB file exceeds the 2M constraint
        $oversizedFile = $this->createDummyFile('huge_cni.pdf', 'application/pdf', 3 * 1024 * 1024, "%PDF-1.4\n");

        $candidature = new Candidature();
        $form = $this->formFactory->create(CandidatureType::class, $candidature, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'fpiece' => $oversizedFile
        ], false);

        $this->assertFalse($form->isValid(), 'Form must be invalid when an uploaded file exceeds 2MB');
        $fieldErrors = $form->get('fpiece')->getErrors();
        $this->assertGreaterThan(0, count($fieldErrors), 'Expected validation error on oversized document');
        $this->assertStringContainsString('trop volumineux', (string)$fieldErrors[0]->getMessage());
    }

    public function testInvalidMimeTypeIsRejected(): void
    {
        // Executable / shell script / PHP file must be rejected
        $maliciousFile = $this->createDummyFile('script.exe', 'application/x-dosexec', 1024, "MZ\x90\x00");

        $candidature = new Candidature();
        $form = $this->formFactory->create(CandidatureType::class, $candidature, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'fpiece' => $maliciousFile
        ], false);

        $this->assertFalse($form->isValid(), 'Executable or invalid MIME type files must be rejected');
        $fieldErrors = $form->get('fpiece')->getErrors();
        $this->assertGreaterThan(0, count($fieldErrors));
        $this->assertStringContainsString('Format', (string)$fieldErrors[0]->getMessage());
    }

    public function testValidPdfDocumentIsAccepted(): void
    {
        // 100 KB valid PDF file with standard PDF header and EOF
        $pdfHeader = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n";
        $validPdf = $this->createDummyFile('piece_valide.pdf', 'application/pdf', 50 * 1024, $pdfHeader);

        $candidature = new Candidature();
        $form = $this->formFactory->create(CandidatureType::class, $candidature, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'fpiece' => $validPdf
        ], false);

        $fieldErrors = $form->get('fpiece')->getErrors();
        $this->assertCount(0, $fieldErrors, 'Valid PDF document under 2MB must have 0 errors');
        $this->assertTrue($form->get('fpiece')->isValid(), 'Valid PDF document under 2MB must pass validation');
    }

    public function testValidJpegImageIsAccepted(): void
    {
        // Valid JPEG header (\xFF\xD8\xFF\xE0)
        $jpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00\xFF\xDB";
        $validJpeg = $this->createDummyFile('photo.jpg', 'image/jpeg', 20 * 1024, $jpegHeader);

        $candidature = new Candidature();
        $form = $this->formFactory->create(CandidatureType::class, $candidature, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'fniveau' => $validJpeg
        ], false);

        $fieldErrors = $form->get('fniveau')->getErrors();
        $this->assertCount(0, $fieldErrors, 'Valid JPEG file under 2MB must have 0 errors');
        $this->assertTrue($form->get('fniveau')->isValid(), 'Valid JPEG document must pass validation');
    }
}
