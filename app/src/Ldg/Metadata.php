<?php

namespace Ldg;

class Metadata
{
	protected $exif = false;
	protected $iptc = false;

	public function __construct($path)
	{
		if (file_exists($path))
		{
			$this->exif = @exif_read_data($path);

			getimagesize($path, $info);

			$arrData = array();
			if (isset($info['APP13']))
			{
				$this->iptc = iptcparse($info['APP13']);
			}
		}
	}

	public function getOrientation()
	{
		if ($this->exif && isset($this->exif['Orientation']))
		{
			return $this->exif['Orientation'];
		}

		return false;
	}

	public function getKeywords()
	{
		if ($this->iptc && isset($this->iptc['2#025']))
		{
			return $this->iptc['2#025'];
		}
	}

	public function getRawExifData()
	{
		if ($this->exif)
		{
			return $this->exif;
		}

		return false;
	}

	public function getRawIptcData()
	{
		if ($this->iptc)
		{
			return $this->iptc;
		}

		return false;
	}

	public function getDateTaken()
	{
		if (isset($this->exif['DateTimeOriginal'])) {
			return strtotime($this->exif['DateTimeOriginal']);
		}
	}

	public function getTakenDateFormatted()
	{
		$text = strftime('%c', $this->exif['FileDateTime']);
		$text .= '-' . $this->exif['DateTime'];
		$text .= '-' . $this->exif['DateTimeOriginal'];
		$text .= '-' . $this->exif['DateTimeDigitized'];

		return $text;
	}
}