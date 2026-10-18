<?php

namespace App\Tests\Unit\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class FileUploaderTest extends TestCase
{
    private string $testUploadDir;
    private FileUploader $uploader;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->testUploadDir = sys_get_temp_dir() . '/pacte_test_uploads_' . uniqid();
        $this->filesystem->mkdir($this->testUploadDir);

        $slugger = new AsciiSlugger();
        $this->uploader = new FileUploader($slugger, $this->filesystem);
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->testUploadDir)) {
            $this->filesystem->remove($this->testUploadDir);
        }
    }

    private function createFakeUploadedFile(string $originalName, string $content = 'dummy binary content'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_file_');
        file_put_contents($tempPath, $content);

        return new UploadedFile(
            $tempPath,
            $originalName,
            'application/pdf',
            null,
            true // test mode: allows moving the file
        );
    }

    public function testUploadNullReturnsPrevious(): void
    {
        $result = $this->uploader->upload(null, $this->testUploadDir, 'previous_file.pdf');
        $this->assertSame('previous_file.pdf', $result);
    }

    public function testUploadGeneratesUniqueNameAndPreventsOverwriting(): void
    {
        // Simulate two different candidates uploading a file with the exact same name "cv.pdf"
        $file1 = $this->createFakeUploadedFile('cv.pdf', 'Content of Candidate 1');
        $file2 = $this->createFakeUploadedFile('cv.pdf', 'Content of Candidate 2');

        $fileName1 = $this->uploader->upload($file1, $this->testUploadDir);
        $fileName2 = $this->uploader->upload($file2, $this->testUploadDir);

        $this->assertNotNull($fileName1);
        $this->assertNotNull($fileName2);
        $this->assertNotSame($fileName1, $fileName2, 'Filenames must be uniquely generated to prevent collisions');

        $this->assertFileExists($this->testUploadDir . '/' . $fileName1);
        $this->assertFileExists($this->testUploadDir . '/' . $fileName2);

        $this->assertSame('Content of Candidate 1', file_get_contents($this->testUploadDir . '/' . $fileName1));
        $this->assertSame('Content of Candidate 2', file_get_contents($this->testUploadDir . '/' . $fileName2));
    }

    public function testUploadDeletesPreviousFile(): void
    {
        // First upload
        $file1 = $this->createFakeUploadedFile('cni.pdf', 'Initial CNI');
        $fileName1 = $this->uploader->upload($file1, $this->testUploadDir);
        $this->assertFileExists($this->testUploadDir . '/' . $fileName1);

        // Update upload replacing fileName1
        $file2 = $this->createFakeUploadedFile('cni_updated.pdf', 'Updated CNI');
        $fileName2 = $this->uploader->upload($file2, $this->testUploadDir, $fileName1);

        $this->assertFileExists($this->testUploadDir . '/' . $fileName2);
        $this->assertFileDoesNotExist($this->testUploadDir . '/' . $fileName1, 'Previous file must be cleaned up');
    }

    public function testUploadWithOriginalNameSanitizesFilename(): void
    {
        $file = $this->createFakeUploadedFile('mon diplôme d\'état (2025).pdf');
        $fileName = $this->uploader->uploadWithOriginalName($file, $this->testUploadDir);

        $this->assertNotNull($fileName);
        $this->assertStringStartsWith('mon-diplome-d-etat-2025', $fileName);
        $this->assertFileExists($this->testUploadDir . '/' . $fileName);
    }

    public function testUploadCreatesDirectoryIfNotExists(): void
    {
        $nestedDir = $this->testUploadDir . '/nested/subfolder';
        $this->assertFalse(is_dir($nestedDir));

        $file = $this->createFakeUploadedFile('fiche.pdf');
        $fileName = $this->uploader->upload($file, $nestedDir);

        $this->assertTrue(is_dir($nestedDir));
        $this->assertFileExists($nestedDir . '/' . $fileName);
    }
}
