<?php

namespace App\Services;

class GoogleVisionService
{
    public function extractText(string $imageUrl): string
    {
        try {
            // Google Cloud kimlik doğrulama
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage_path('app/vital-scan-vscan-f84aa6680e43.json'));

            // Client oluştur
            $imageAnnotator = new \Google\Cloud\Vision\V1\Client\ImageAnnotatorClient();

            $imageContent = file_get_contents($imageUrl);

            // Image nesnesini oluştur
            $image = new \Google\Cloud\Vision\V1\Image();
            $image->setContent($imageContent);

            // Feature nesnesi oluştur - TEXT_DETECTION özelliğini belirt
            $feature = new \Google\Cloud\Vision\V1\Feature();
            $feature->setType(\Google\Cloud\Vision\V1\Feature\Type::TEXT_DETECTION);

            // AnnotateImageRequest oluştur
            $request = new \Google\Cloud\Vision\V1\AnnotateImageRequest();
            $request->setImage($image);
            $request->setFeatures([$feature]);

            // BatchAnnotateImagesRequest oluştur
            $batchRequest = new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$request]);

            // İsteği gönder
            $response = $imageAnnotator->batchAnnotateImages($batchRequest);

            // Yanıtı işle
            $annotations = $response->getResponses()[0];
            $textAnnotations = $annotations->getTextAnnotations();

            $imageAnnotator->close();

            // Sonucu göster
            if (count($textAnnotations) > 0) {
                $text = $textAnnotations[0]->getDescription();
                return $text;
            } else {
                return "Not found text";
            }

        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
