<?php
use Eventviva\ImageResize;

require_once './vendor/autoload.php';

// Create and configure Slim app
$app = new \Slim\App(
    [
        'repo' => '/media/forum.ruranobe.ru/images/',
    ]
);

/**
 * @param string              $repo
 * @param string              $file
 * @param int                 $width
 * @param bool                $archived
 * @param \Slim\Http\Response $response
 * @return mixed
 */
function thumb($repo, $file, $width, $archived, $response, $format)
{
    $md5  = md5($file);
    $file = $md5[0] . '/' . $md5[0] . $md5[1] . '/' . $file;

    $path = $repo . ($archived ? 'archive/' : '') . $file;
    if (is_readable($path)) {
        $path      = realpath($path);
        $pathParts = pathinfo($path);
        if (strpos($pathParts['dirname'], $repo) === 0) {
            $cacheDir = $repo . 'thumb/' . ($archived ? 'archive/' : '') . $file;
            if (!is_dir($cacheDir)) {
                if (!mkdir($cacheDir, 0777, true)) {
                    return $response->withStatus(403);
                }
            }
            if ($format != 'jpg') {
                $cacheFile = $cacheDir . '/' . $width . 'px-' . $pathParts['basename'];
            } else {
                $cacheFile = $cacheDir . '/' . $width . 'px-' . $pathParts['filename'] . '.jpg';
            }
            if (!is_readable($cacheFile)) {
                $image = new ImageResize($path);
                if ($width > $image->getSourceWidth()) {
                    return $response->withRedirect('/images/' . $file);
                } else {
                    $image->resizeToWidth($width);
                    if ($format != 'jpg') {
                        $image->save($cacheFile);
                    } else {
                        $image->save($cacheFile, IMAGETYPE_JPEG);
                    }
                }
            }
            $finfo  = finfo_open(FILEINFO_MIME_TYPE);
            $type   = finfo_file($finfo, $cacheFile);
            $stream = new \GuzzleHttp\Psr7\LazyOpenStream($cacheFile, 'r');
            return $response->withHeader('Content-type', $type)->withBody($stream);
        }
    }
    return $response->withStatus(404);
}

// Define app routes
$app->get(
    '/images/thumb/archive/{md5_0}/{md5_01}/{file}/{width:[0-9]+}px-{file1}',
    function ($request, $response, $args) {
        /** @var \Slim\Http\Request $request */
        /** @var \Slim\Http\Response $response */

        $file  = $args['file'];
        $width = intval($args['width'] ?: 1080);
        $format = pathinfo($args['file1'], PATHINFO_EXTENSION);
        return thumb($this->repo, $file, $width, true, $response, $format);
    }
);
$app->get(
    '/images/thumb/{md5_0}/{md5_01}/{file}/{width:[0-9]+}px-{file1}',
    function ($request, $response, $args) {
        /** @var \Slim\Http\Request $request */
        /** @var \Slim\Http\Response $response */

        $file  = $args['file'];
        $width = intval($args['width'] ?: 1080);
        $format = pathinfo($args['file1'], PATHINFO_EXTENSION);
        return thumb($this->repo, $file, $width, false, $response, $format);
    }
);

// Run app
$app->run();