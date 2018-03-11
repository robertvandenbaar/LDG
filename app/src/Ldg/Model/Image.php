<?php

namespace Ldg\Model;


class Image extends File
{

	public function getExtension()
	{
		$parts = explode('.', $this->path);
		return strtolower(end($parts));
	}

	public function getPreferredUrl()
	{
		if (isset($_SESSION['full-size']) && $_SESSION['full-size'] === true)
		{
			return $this->getOriginalUrl();
		}
		else
		{
			return $this->getDetailUrl();
		}
	}

	public function getDetailPath()
	{
		$detailCacheFile = BASE_DIR . '/' . 'cache' . '/' . 'detail';
		$detailCacheFile .= $this->getRelativeLocation();

		return $detailCacheFile;
	}

	public function getDetailUrl()
	{
		$detailCacheFile = $this->getDetailPath();

		if (file_exists($detailCacheFile))
		{
			return BASE_URL . '/cache/detail' . $this->getRelativeLocation();
		}
		else
		{
			return BASE_URL . '/detail' . $this->getRelativeLocation();
		}

	}

	public function isDetailCurrent()
	{
		return file_exists($this->getDetailPath()) && filectime($this->getDetailPath()) >= filectime($this->getPath());
	}

	public function getOriginalUrl()
	{
		return BASE_URL . '/original' . $this->getRelativeLocation();
	}

	public function getThumbnailPath()
	{
		$thumbnailPath = BASE_DIR . '/' . 'cache' . '/' . 'thumbnail';

		$thumbnailPath .= $this->getRelativeLocation();

		return $thumbnailPath;
	}

	public function getThumbnailUrl()
	{

		if (file_exists($this->getThumbnailPath()))
		{
			return BASE_URL . '/cache/thumbnail' . str_replace(\Ldg\Setting::get('image_base_dir'), '', $this->path);
		}
		else
		{
			return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
		}
	}

	public function isThumbnailCurrent()
	{
		return file_exists($this->getThumbnailPath()) && filectime($this->getThumbnailPath()) >= filectime($this->getPath());
	}

	public function updateThumbnail()
	{
		if (!$this->fileExists())
		{
			\Ldg\Log::addEntry('error','Cannot create thumbnail, file: ' . $this->getPath() . ' does not exist');
			return false;
		}

		if (!$this->isValidPath())
		{
			\Ldg\Log::addEntry('error','Image does not have a valid path: ' . $this->getPath());
			return false;
		}

		$exif = $this->getExif();

		$thumbnailPath = $this->getThumbnailPath();

		// thumbnail file exits and is newer than the original, no action required
		if (file_exists($thumbnailPath) && filemtime($thumbnailPath) >= filemtime($this->getPath()))
		{
			return true;
		}

		$thumbnailDir = dirname($thumbnailPath);

		if (!is_dir($thumbnailDir))
		{
			if (!mkdir($thumbnailDir, 0777, true))
			{
				throw new Exception('Could not create cache file directory: ' . $thumbnailDir);
			}
		}

		try
		{
			$image = new \vakata\image\Image(file_get_contents($this->getPath()));

			if ($exif && \Ldg\Setting::get('auto_rotate'))
			{
				$this->fixOrientation($image, $exif);
			}

			$resizedImage = $image->crop(
				\Ldg\Setting::get('thumbnail_width'),
				\Ldg\Setting::get('thumbnail_height')
			)->toJpg();


			if (!file_put_contents($this->getThumbnailPath(), $resizedImage))
			{
				\Ldg\Log::addEntry('error', 'Could not save thumbnail from image: ' . $this->getRelativeLocation());
			}
		}
		catch(\Exception $e)
		{
			\Ldg\Log::addEntry('error', 'Could create thumbnail from image: ' . $this->getRelativeLocation() . '. ' . $e->getMessage());
			return false;
		}

		return file_exists($this->getThumbnailPath());
	}

	public function updateIndex()
	{
		$exif = $this->getExif();

		$data = $this->getRelativeLocation();

		if (isset($exif) && $exif && $exif->getKeywords())
		{
			$data .= ' ' . implode(' ', (array)$exif->getKeywords());
		}

		$search = new \Ldg\Search();
		$search->setEntry($this->getRelativeLocation(), $data);
		$search->save();

	}

	public function updateDetail()
	{
		if (!$this->fileExists())
		{
			\Ldg\Log::addEntry('error','Cannot create detail, file: ' . $this->getPath() . ' does not exist');
			return false;
		}

		if (!$this->isValidPath())
		{
			\Ldg\Log::addEntry('error', 'Image does not have a valid path: ' . $this->getPath());
			return false;
		}

		$detailPath = $this->getDetailPath();

		// detail file exits and is newer than the original, no action required
		if (file_exists($detailPath) && filemtime($detailPath) >= filemtime($this->getPath()))
		{
			return true;
		}

		$detailDir = dirname($detailPath);

		$exif = $this->getExif();

		if (!is_dir($detailDir))
		{
			if (!mkdir($detailDir, 0777, true))
			{
				throw new Exception('Could not create cache file directory: ' . $detailDir);
			}
		}

		try
		{
			$image = new \vakata\image\Image(file_get_contents($this->getPath()));

			if ($exif && \Ldg\Setting::get('auto_rotate'))
			{
				$this->fixOrientation($image, $exif);
			}

			$resizedImage = $image->crop(\Ldg\Setting::get('detail_width'))->toJpg();

			if (!file_put_contents($this->getDetailPath(), $resizedImage))
			{
				\Ldg\Log::addEntry('error', 'Could not save detail image from image: ' . $this->getRelativeLocation());
			}
		}
		catch (\Exception $e)
		{
			\Ldg\Log::addEntry('error', 'Could create detail image from image: ' . $this->getRelativeLocation() . '. ' . $e->getMessage());

			return false;
		}

		return file_exists($this->getDetailPath());
	}

	public function getExif()
	{
		try
		{
			$reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
			$exif = $reader->read($this->getPath());
		}
		catch (\Exception $e)
		{
			\Ldg\Log::addEntry('notice', 'Could not read exif information from image: ' . $this->getPath());
			return false;
		}

		return $exif;
	}

	public function fixOrientation(\vakata\image\Image $image, $exif)
	{
		// imagick and GD handle this different
		if (extension_loaded('imagick'))
		{
			$degrees = [3 => 180, 6 => 90, 8 => -90];
		}
		else
		{
			$degrees = [3 => 180, 6 => -90, 8 => 90];
		}

		if ($exif)
		{
			if (array_key_exists($exif->getOrientation(), $degrees))
			{
				$image->rotate($degrees[$exif->getOrientation()]);
			}
		}

	}
}