<?php

namespace Ldg;

class App
{
	protected $twig;
	protected $setting;
	protected $parts;

	protected $actions = ['list', 'detail', 'original', 'asset', 'update_thumbnail', 'search'];

	function __construct()
	{
		$this->checkPermissions();

		$this->checkFullSize();

		$this->loadTemplate();

	}

	function checkPermissions()
	{
		if (!is_writable(BASE_DIR . '/' . 'cache'))
		{
			throw new \Exception('Cannot write to cache directory: ' . BASE_DIR . '/' . 'cache');
		}

		if (!is_writable(BASE_DIR . '/data'))
		{
			throw new \Exception('Cannot write to data directory: ' . BASE_DIR . '/' . 'data');
		}
	}

	function checkFullSize()
	{
		if (\Ldg\Setting::get('full_size_by_default') === true)
		{
			$_SESSION['full-size'] = true;
		}
		else
		{
			if (isset($_GET['full-size']))
			{
				if ($_GET['full-size'] == 'true')
				{
					$_SESSION['full-size'] = true;
				}
				else
				{
					$_SESSION['full-size'] = false;
				}
				exit;
			}
		}
	}

	function loadTemplate()
	{
		$loader = new \Twig_Loader_Filesystem(BASE_DIR . '/app/src/Ldg/Views');
		$twig = new \Twig_Environment($loader);
		$twig->addGlobal('base_url', BASE_URL);

		$this->twig = $twig;
	}

	function run()
	{
		// get request path
		$uri =  parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

		// find base URL
		if (substr($uri, 0, strlen(BASE_URL)))
		{
			$uri = substr($uri, strlen(BASE_URL));
		}

		$this->parts = explode('/', $uri);
		$this->parts = array_map('urldecode', $this->parts);

		// default action
		if (isset($this->parts[1]) && in_array($this->parts[1], $this->actions))
		{
			$action = $this->parts[1];
		}
		else
		{
			$action = 'list';
		}

		unset($this->parts[0]);

		switch ($action)
		{
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
		}
	}

	function renderDetail()
	{
		unset($this->parts[1]);

		$image = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

		$image->updateIndex();

		// update the cache image so next time it won't have to go through php
		if ($image->updateDetail())
		{
			header('Content-Type: image/jpeg');
			readfile($image->getDetailPath());
		}
		else
		{
			if (!$image->fileExists())
			{
				\Ldg\Log::addEntry('error','Cannot render detail, file: ' . $this->getPath() . ' does not exist');
				return;
			}

			if (!$image->isValidPath())
			{
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

		if (!in_array($file->getExtension(), $this->imageExtensions))
		{
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename='.basename($file->getPath()));
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

		if (count($this->parts) > 0)
		{
			$listBaseDir .= '/' . implode('/', $this->parts);
		}

		if (!is_dir($listBaseDir))
		{
			header("HTTP/1.0 404 Not Found");
			http_response_code(404);

			$variables['message'] = 'File not found, please return to the ';
			$variables['link'] = BASE_URL . '/';

			echo $this->twig->render('notfound.twig', $variables);
			return;
		}

		$folders = $images = $otherFiles = [];

		$files = scandir($listBaseDir);

		foreach ($files as $file)
		{
			// skip hidden files and directories
			if (substr($file, 0, 1) == '.')
			{
				continue;
			}

			$fullPath = $listBaseDir . '/' . $file;

			if (is_dir($fullPath))
			{
				$folders[] = new \Ldg\Model\Folder($fullPath);
			}
			else
			{
				$file = new \Ldg\Model\File($fullPath);
				$extension = $file->getExtension();

				if (in_array($extension, \Ldg\Setting::get('supported_extensions')))
				{
					$images[] = new \Ldg\Model\Image($fullPath);
				}
				else
				{
					$otherFiles[] = $file;
				}
			}
		}

		$breadCrumbParts = [];

		$buildPart = '';

		$i = 0;

		foreach($this->parts as $part)
		{
			$buildPart .= '/' . $part;

			$breadCrumbParts[] = new \Ldg\Model\BreadcrumbPart(BASE_URL . $buildPart, $part, ++$i == count($this->parts));
		}

		$variables = [
			'folders' => $folders,
			'images' => $images,
			'other_files' => $otherFiles,
			'breadcrumb_parts' => $breadCrumbParts,
		];

		if (count($this->parts) > 0)
		{
			$variables['folder_up'] = new \Ldg\Model\Folder(dirname($listBaseDir));
		}

		echo $this->twig->render('list.twig', $variables);

	}

	function updateThumbnail()
	{
		unset($this->parts[1]);

		$image = new \Ldg\Model\Image(\Ldg\Setting::get('image_base_dir') . '/' . implode('/', $this->parts));

		if ($image->updateThumbnail())
		{
			http_response_code();
			echo 'success';
		}
		else
		{
			http_response_code(500);
			echo 'fail';
		}

		exit;
	}

	function renderSearch()
	{
		$index = new \Ldg\Search();
		$results = $index->search($_POST['q']);

		$images = [];

		foreach ($results as $path => $result)
		{
			$imagePath = \Ldg\Setting::get('image_base_dir') . $path;

			// check if the file hasn't been deleted after last index
			if (file_exists($imagePath))
			{
				$images[] = new \Ldg\Model\Image($imagePath);
			}
		}

		$variables = [
			'images' => $images
		];

		echo $this->twig->render('search.twig', $variables);

	}

}