<?php

namespace Ldg;

use Ldg\Model\File;
use Ldg\Model\Image;

class App
{
    protected $twig;
    protected $setting;
    protected $parts;

    protected $actions = ['list', 'detail', 'original', 'update_thumbnail', 'search', 'info', 'rotate', 'video_stream', 'map_view'];

    public function __construct()
    {
        session_start();

        $this->checkPermissions();

        $this->checkFullSize();

        $this->loadTemplate();
    }

    public function checkPermissions()
    {
        if (!is_writable(BASE_DIR . '/' . 'cache')) {
            throw new \Exception('Cannot write to cache directory: ' . BASE_DIR . '/' . 'cache');
        }

        if (!is_writable(BASE_DIR . '/data')) {
            throw new \Exception('Cannot write to data directory: ' . BASE_DIR . '/' . 'data');
        }
    }

    public function checkFullSize()
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

    public function loadTemplate()
    {
        $loader = new \Twig\Loader\FilesystemLoader([BASE_DIR . '/app/src/Ldg/Views']);
        $twig = new \Twig\Environment($loader);
        $twig->addGlobal('base_url', BASE_URL);
        $twig->addGlobal('base_url_ldg', BASE_URL_LDG);
        $twig->addGlobal('full_size', $_SESSION['full-size']);
        $twig->addGlobal('version_ts', filemtime(BASE_DIR . '/assets/js/app.js') + filemtime(BASE_DIR . '/assets/css/style.css'));

        $this->twig = $twig;
    }

    public function run()
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

        $action = 'list';
        if (isset($this->parts[1]) && $this->parts[1] == 'ldg' && isset($this->parts[2]) && in_array($this->parts[2], $this->actions)) {
            $action = $this->parts[2];
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
            case 'rotate':
                $this->renderRotate();
                break;
            case 'video_stream':
                $this->videoStream();
                break;
            case 'map_view':
                $this->renderMap();
                break;

        }
    }

    public function renderMap()
    {
        $search = new Search();
        $search->loadIndex();
        $images = $search->getImagesWithGeo();

        $variables = ['images' => $images];
        echo $this->twig->render('map.twig', $variables);
    }

    public function renderDetail()
    {
        $this->removeActionUrlParts();

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

    public function renderOriginal()
    {
        $this->removeActionUrlParts();

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

    public function videoStream()
    {
        $this->removeActionUrlParts();

        $file = new \Ldg\Model\File(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        if (!$file->isValidPath()) {
            return false;
        }

        if (!$file->fileExists()) {
            return false;
        }

        $mimeType = \Defr\PhpMimeType\MimeType::get(new \SplFileInfo($file->getPath()));

        if ($mimeType == 'audio/mp4') {
            $mimeType = 'video/mp4';
        }

        $file = $file->getPath();
        $fp = @fopen($file, 'rb');
        $size   = filesize($file);
        $length = $size;
        $start = 0;
        $end = $size - 1;

        header('Content-type: ' . $mimeType);
        header("Accept-Ranges: bytes");

        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end   = $end;
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }

            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            } else {
                $range  = explode('-', $range);
                $c_start = $range[0];
                $c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
            }

            $c_end = ($c_end > $end) ? $end : $c_end;

            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            $start  = $c_start;
            $end    = $c_end;
            $length = $end - $start + 1;
            fseek($fp, $start);
            header('HTTP/1.1 206 Partial Content');
        }
        header("Content-Range: bytes $start-$end/$size");
        header("Content-Length: " . $length);

        $buffer = 1024 * 8;

        while(!feof($fp) && ($p = ftell($fp)) <= $end) {
            if ($p + $buffer > $end) {
                $buffer = $end - $p + 1;
            }
            set_time_limit(0);
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit();
    }

    public function renderList()
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

        if (!isset($_SESSION['order'])) {
            if (Setting::get('order_by_date_taken')) {
                $_SESSION['order'] = 'date_taken';
            } else {
                $_SESSION['order'] = 'filename';
            }
            
            $_SESSION['direction'] = 'asc';
        }

        if (isset($_GET['order']) && in_array($_GET['order'], ['filename', 'date_taken'])) {
            $_SESSION['order'] = $_GET['order'];
        }
        if (isset($_GET['direction']) && in_array($_GET['direction'], ['asc', 'desc'])) {
            $_SESSION['direction'] = $_GET['direction'];
        }
                        
        $files = scandir($listBaseDir, $_SESSION['direction'] == 'desc' ? SCANDIR_SORT_DESCENDING : null);

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
                } elseif (in_array($extension, \Ldg\Setting::get('supported_video_extensions'))) {
                    $otherFiles[] = new \Ldg\Model\Video($fullPath);
                } else {
                    $otherFiles[] = $file;
                }
            }
        }

        if ($_SESSION['order'] == 'date_taken') {
            usort($images, function ($a, $b) {

                $dateTakenA = $a->getMetadata()->getDateTaken();
                $dateTakenB = $b->getMetadata()->getDateTaken();

                if ($dateTakenA == null || $dateTakenB == null || ($dateTakenA == $dateTakenB)) {
                    return 0;
                }

                if ($_SESSION['direction'] == 'desc') {
                    return $dateTakenA < $dateTakenB ? 1 : -1;
                } else {
                    return $dateTakenA < $dateTakenB ? -1 : 1;
                }

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

        $latestImages = $onThisDay = [];

        if (count($images) == 0 && count($this->parts) == 0) {
            $index = new \Ldg\Search();
            $latestImages = $index->getLatestImages();
            $onThisDay = $index->getOnThisDay();
            $randomImages = $index->getRandom();
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
            'on_this_day' => $onThisDay,
            'random_images' => $randomImages,
            'session' => $_SESSION
        ];

        $variables = $variables + $this->getDefaultListVariables($search);

        if (count($this->parts) > 0) {
            $variables['folder_up'] = new \Ldg\Model\Folder(dirname($listBaseDir));
        }

        echo $this->twig->render('list.twig', $variables);

    }

    public function updateThumbnail()
    {
        $this->removeActionUrlParts();

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

    public function renderSearch()
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
                } elseif (in_array($extension, \Ldg\Setting::get('supported_video_extensions'))) {
                    $otherFiles[] = new \Ldg\Model\Video($fullPath);
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
        ];

        $variables = $variables + $this->getDefaultListVariables($index);

        $forwardRequestParameters = ['q', 'limit_to_keyword_search', 'camera', 'lens', 'year', 'month', 'day'];

        foreach ($forwardRequestParameters as $parameter) {
            $variables[$parameter] = isset($_REQUEST[$parameter]) ? $_REQUEST[$parameter] : false;
        }

        echo $this->twig->render('search.twig', $variables);

    }

    public function getDefaultListVariables($index)
    {
        $years = range(2000, date('Y'));
        $months = range(1,12);
        $monthsNames = [];
        foreach ($months as $month) {
            $monthsNames[] = strftime('%b', mktime(1, 1, 1, $month, 1));
        }
        $days = range(1,31);
        return [
            'index_count' => $index->getIndexCount(),
            'unique_cams' => $index->getUniqueCameras(),
            'unique_lenses' => $index->getUniqueLenses(),
            'filter_active' => $index->hasFilter(),
            'years' => array_combine($years, $years),
            'months' => array_combine($months, $monthsNames),
            'days' => array_combine($days, $days),
        ];
    }

    public function getPage()
    {
        $page = 1;

        if (isset($_REQUEST['page'])) {
            $page = max(1, intval($_REQUEST['page']));
        }

        return $page;
    }

    public function removeActionUrlParts()
    {
        unset($this->parts[1]);
        unset($this->parts[2]);
    }

    public function renderInfo()
    {
        $this->removeActionUrlParts();

        $file = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

        if (!$file->fileExists() || !$file->isValidPath()) {
            echo json_encode(['result' => false, 'error' => 'File ' . $file->getPath() . 'could not be found']);
            exit;
        }

        $metadata = $file->getMetadata();

        $response = [
            'result' => true,
            'file' => trim($file->getFolderName(), '/') . '/' . $file->getName(),
        ];

        $data = [
            'Keywords' => $metadata->getKeywords(),
            'Camera Make' => $metadata->getMake(),
            'Camera Model' => $metadata->getModel(),
            'Lens' => $metadata->getLens(),
            'Date taken' => $metadata->getDateTaken(),
            'Shutterspeed' => $metadata->getFormattedShutterSpeed($metadata->getShutterSpeed()),
            'Aperture' => $metadata->getFormattedAperture($metadata->getAperture()),
            'ISO' => $metadata->getIso(),
            'Focal Length' => $metadata->getFormattedFocalLength($metadata->getFocalLength()),
            'GPS' => $metadata->getGpsData(),
            'Exposure' => trim($metadata->getExposureMode() . ', ' . $metadata->getExposureProgram(), ', '),
            'File Date' => $metadata->getDateFile(),
            'Orignal File Size' => $metadata->getFileSize(),
            'Original dimensions' => $metadata->getHeight() > 0 && $metadata->getWidth() > 0 ? $metadata->getHeight() . ' x ' . $metadata->getWidth() : false
        ];

        $response['data'] = $data;

        echo json_encode($response);
        exit;
    }

    public function renderRotate()
    {
        $this->removeActionUrlParts();

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