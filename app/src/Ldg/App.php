<?php

namespace Ldg;

use Ldg\Model\File;
use Ldg\Model\Image;

class App
{
    protected $twig;
    protected $setting;
    protected $parts;

    protected $actions = ['list', 'detail', 'original', 'asset', 'update_thumbnail', 'search', 'info', 'info_detail', 'rotate'];

    function __construct()
    {
        session_start();

        $this->checkPermissions();

        $this->checkFullSize();

        $this->loadTemplate();
    }

    function checkPermissions()
    {
        if (!is_writable(BASE_DIR . '/' . 'cache')) {
            throw new \Exception('Cannot write to cache directory: ' . BASE_DIR . '/' . 'cache');
        }

        if (!is_writable(BASE_DIR . '/data')) {
            throw new \Exception('Cannot write to data directory: ' . BASE_DIR . '/' . 'data');
        }
    }

    function checkFullSize()
    {
        // overwrite full-size option
        if (isset($_GET['full-size'])) {
            if ($_GET['full-size'] == 'true') {
                $_SESSION['full-size'] = true;
            } else {
                $_SESSION['full-size'] = false;
            }
            exit;
        } else {
            // this is a new session, set the default value
            if (!isset($_SESSION['full-size'])) {
                $_SESSION['full-size'] = \Ldg\Setting::get('full_size_by_default');
            }
        }
    }

    function loadTemplate()
    {
        $loader = new \Twig_Loader_Filesystem(BASE_DIR . '/app/src/Ldg/Views');
        $twig = new \Twig_Environment($loader);
        $twig->addGlobal('base_url', BASE_URL);
        $twig->addGlobal('full_size', $_SESSION['full-size']);

        $this->twig = $twig;
    }

    function run()
    {
        // set encoding
        mb_internal_encoding('UTF-8');
        setlocale(LC_ALL, "en_US.UTF-8");

        // get request path
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // find base URL
        if (substr($uri, 0, strlen(BASE_URL))) {
            $uri = substr($uri, strlen(BASE_URL));
        }

        $this->parts = explode('/', $uri);
        $this->parts = array_map('urldecode', $this->parts);

        // default action
        if (isset($this->parts[1]) && in_array($this->parts[1], $this->actions)) {
            $action = $this->parts[1];
        } else {
            $action = 'list';
        }

        unset($this->parts[0]);

        switch ($action) {
            case 'list':
                $this->renderList();
                break;
            case 'detail':
                $this->renderDetail();
                break;
            case 'original':
                $this->renderOriginal();
                break;
            case 'update_thumbnail':
                $this->updateThumbnail();
                break;
            case 'search':
                $this->renderSearch();
                break;
            case 'info':
                $this->renderInfo();
                break;
            case 'info_detail':
                $this->renderInfoDetail();
                break;
            case 'rotate':
                $this->renderRotate();
                break;
        }
    }

    function renderDetail()
    {
        unset($this->parts[1]);

        $image = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        // update the cache image so next time it won't have to go through php
        if ($image->updateDetail()) {
            header('Content-Type: image/jpeg');
            readfile($image->getDetailPath());
        } else {
            if (!$image->fileExists()) {
                \Ldg\Log::addEntry('error', 'Cannot render detail, file: ' . $this->getPath() . ' does not exist');
                return;
            }

            if (!$image->isValidPath()) {
                \Ldg\Log::addEntry('error', 'Image does not have a valid path: ' . $this->getPath());
                return;
            }

            header('Content-Type:' . \Defr\PhpMimeType\MimeType::get(new \SplFileInfo($image->getPath())));
            readfile($image->getPath());
        }

        exit;

    }

    function renderOriginal()
    {
        unset($this->parts[1]);

        $file = new \Ldg\Model\File(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        header('Content-Type:' . \Defr\PhpMimeType\MimeType::get(new \SplFileInfo($file->getPath())));

        if (!in_array($file->getExtension(), \Ldg\Setting::get('supported_extensions'))) {
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename=' . basename($file->getPath()));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file->getPath()));
        }

        ob_clean();
        flush();

        readfile($file->getPath());

        exit;
    }

    function renderList()
    {
        $this->parts = array_filter($this->parts, 'strlen');

        $listBaseDir = \Ldg\Setting::get('image_base_dir');

        if (count($this->parts) > 0) {
            $listBaseDir .= '/' . implode('/', $this->parts);
        }

        if (!is_dir($listBaseDir)) {
            header("HTTP/1.0 404 Not Found");
            http_response_code(404);

            $variables['message'] = 'File not found, please return to the ';
            $variables['link'] = BASE_URL . '/';

            echo $this->twig->render('notfound.twig', $variables);
            return;
        }

        $folders = $images = $otherFiles = [];

        $files = scandir($listBaseDir);

        foreach ($files as $file) {
            // skip hidden files and hidden directories
            if (substr($file, 0, 1) == '.') {
                continue;
            }

            $fullPath = $listBaseDir . '/' . $file;

            if (is_dir($fullPath)) {
                $folders[] = new \Ldg\Model\Folder($fullPath);
            } else {
                $file = new \Ldg\Model\File($fullPath);
                $extension = $file->getExtension();

                if (in_array($extension, \Ldg\Setting::get('supported_extensions'))) {
                    $images[] = new \Ldg\Model\Image($fullPath);
                } else {
                    $otherFiles[] = $file;
                }
            }
        }

        if (Setting::get('order_by_date_taken')) {
            usort($images, function ($a, $b) {

                $dateTakenA = $a->getMetadata()->getDateTaken();
                $dateTakenB = $b->getMetadata()->getDateTaken();

                if ($dateTakenA == null || $dateTakenB == null || ($dateTakenA == $dateTakenB)) {
                    return 0;
                }

                return $dateTakenA < $dateTakenB ? -1 : 1;

            });
        }

        $breadCrumbParts = [];

        $buildPart = '';

        $i = 0;

        foreach ($this->parts as $part) {
            $buildPart .= '/' . $part;

            $breadCrumbParts[] = new \Ldg\Model\BreadcrumbPart(BASE_URL . $buildPart, $part,
                ++$i == count($this->parts));
        }

        $imagesPerPage = Setting::get('images_per_page');

        $pagination = new \Ldg\Pagination();
        $pagination->totalItems = count($images);
        $pagination->currentPage = $this->getPage();
        $pagination->itemsPerPage = $imagesPerPage;

        $latestImages = [];

        if (count($images) == 0 && count($this->parts) == 0) {
            $index = new \Ldg\Search();
            $latestImages = $index->getLatestFiles();
        }

        $images = array_slice($images, ($this->getPage() - 1) * $imagesPerPage, $imagesPerPage);

        $search = new Search();

        $variables = [
            'folders' => $folders,
            'images' => $images,
            'other_files' => $otherFiles,
            'breadcrumb_parts' => $breadCrumbParts,
            'pagination' => $pagination,
            'latest_images' => $latestImages,
            'unique_cams' => $search->getUniqueCameras(),
            'unique_lenses' => $search->getUniqueLenses(),

        ];

        if (count($this->parts) > 0) {
            $variables['folder_up'] = new \Ldg\Model\Folder(dirname($listBaseDir));
        }

        echo $this->twig->render('list.twig', $variables);

    }

    function updateThumbnail()
    {
        unset($this->parts[1]);

        $image = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        if ($image->updateThumbnail()) {
            http_response_code();
            echo 'success';
        } else {
            http_response_code(500);
            echo 'fail';
        }

        exit;
    }

    function renderSearch()
    {
        $index = new \Ldg\Search();
        $results = $index->search($_REQUEST['q']);

        $images = $otherFiles = [];

        foreach ($results as $path => $result) {
            $fullPath = \Ldg\Setting::get('image_base_dir') . $path;

            // check if the file hasn't been deleted after last index
            if (file_exists($fullPath)) {
                $file = new \Ldg\Model\File($fullPath);
                $extension = $file->getExtension();

                if (in_array($extension, \Ldg\Setting::get('supported_extensions'))) {
                    $images[] = new \Ldg\Model\Image($fullPath);
                } else {
                    $otherFiles[] = $file;
                }
            }
        }

        $imagesPerPage = Setting::get('images_per_page');

        $pagination = new \Ldg\Pagination();
        $pagination->totalItems = count($images);
        $pagination->currentPage = $this->getPage();
        $pagination->itemsPerPage = $imagesPerPage;

        $totalNrImages = count($images);

        $images = array_slice($images, ($this->getPage() - 1) * $imagesPerPage, $imagesPerPage);

        $variables = [
            'images' => $images,
            'other_files' => $otherFiles,
            'index_count' => $index->getIndexCount(),
            'pagination' => $pagination,
            'total_nr_images' => $totalNrImages,
            'unique_cams' => $index->getUniqueCameras(),
            'unique_lenses' => $index->getUniqueLenses(),
            'filter_active' => $index->hasFilter()
        ];

        $forwardRequestParameters = ['q', 'limit_to_keyword_search', 'camera', 'lens'];

        foreach ($forwardRequestParameters as $parameter) {
            $variables[$parameter] = isset($_REQUEST[$parameter]) ? $_REQUEST[$parameter] : false;
        }

        echo $this->twig->render('search.twig', $variables);

    }

    function getPage()
    {
        $page = 1;

        if (isset($_REQUEST['page'])) {
            $page = max(1, intval($_REQUEST['page']));
        }

        return $page;
    }

    function renderInfo()
    {
        unset($this->parts[1]);
        $file = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        if (!$file->fileExists() || !$file->isValidPath()) {
            echo json_encode(['result' => false, 'error' => 'File ' . $file->getPath() . 'could not be found']);
            exit;
        }

        $metadata = $file->getMetadata();

        $response = [
            'result' => true,
            'file' => trim($file->getFolderName(), '/') . '/' . $file->getName(),
            'link_to_full_exif' => $file->getInfoDetailUrl()
        ];

        $data = [
            'Keywords' => $metadata->getKeywords(),
            'Camera Make' => $metadata->getMake(),
            'Camera Model' => $metadata->getModel(),
            'Lens' => $metadata->getLens(),
            'Date taken' => $metadata->getDateTaken(),
            'Date created' => $metadata->getDate(),
            'Date modified' => $metadata->getDateFile(),
            'Shutterspeed' => $metadata->getFormattedShutterSpeed($metadata->getShutterSpeed()),
            'Aperture' => $metadata->getFormattedAperture($metadata->getAperture()),
            'ISO' => $metadata->getIso(),
            'Focal Length' => $metadata->getFormattedFocalLength($metadata->getFocalLength()),
            'GPS' => $metadata->getGpsData(),
            'Exposure Mode' => $metadata->getExposureMode(),
            'Exposure Program' => $metadata->getExposureProgram(),
            'File size' => $metadata->getFileSize(),
            'Original dimensions' => $metadata->getHeight() > 0 && $metadata->getWidth() > 0 ? $metadata->getHeight() . ' x ' . $metadata->getWidth() : false

        ];

        $response['data'] = $data;

        echo json_encode($response);
        exit;
    }

    function renderInfoDetail()
    {
        unset($this->parts[1]);
        $file = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        if (!$file->fileExists() || !$file->isValidPath()) {
            echo json_encode(['result' => false, 'error' => 'File ' . $file->getPath() . 'could not be found']);
            exit;
        }

        $metadata = $file->getMetadata();

        echo '<body style="margin: 0; padding: 0; background: #000;">';
        echo '<pre style="background: #222; color: #ddd; padding: 10px;">';
        echo '<h1 style="margin: 0;">EXIF</h1>';
        var_dump($metadata->getRawExifData());
        echo '</pre>';
        echo '<pre style="background: #333; color: #aaa; padding: 10px;">';
        echo '<h1 style="margin: 0;">IPTC</h1>';
        var_dump($metadata->getRawIptcData());
        echo '</pre>';
        echo '</body>';
        die();
    }

    function renderRotate()
    {
        unset($this->parts[1]);
        $file = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        if (!$file->fileExists() || !$file->isValidPath()) {
            echo json_encode(['result' => false, 'error' => 'File ' . $file->getPath() . 'could not be found']);
            exit;
        }

        $rotateValue = isset($_GET['invert']) ? -90 : 90;

        $updateDetail = $file->updateDetail(true, $rotateValue);
        $updateThumbnail = $file->updateThumbnail(true);

        $success = $updateDetail && $updateThumbnail;

        $response = ['result' => $success, 'direction' => $rotateValue];

        echo json_encode($response);
        exit;

    }

}