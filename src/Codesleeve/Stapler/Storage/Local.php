<?php namespace Codesleeve\Stapler\Storage;

use Codesleeve\Stapler\Exceptions;
use Illuminate\Support\Facades\Config as Config;

class Local implements StorageInterface
{
	/**
	 * The currenty attachedFile object being processed
	 * 
	 * @var Codesleeve\Stapler\Attachment
	 */
	protected $attachedFile;

	/**
	 * Constructor method
	 * 
	 * @param Codesleeve\Stapler\Attachment $attachedFile
	 */
	function __construct($attachedFile)
	{
		$this->attachedFile = $attachedFile;
	}

	/**
	 * Reset an attached file
	 *
	 * @return void
	 */
	public function reset()
	{
		$directory = $this->findDirectory($this->attachedFile);
		$this->emptyDirectory($directory);
	}

	/**
	 * Remove an attached file.
	 * 
	 * @param  Codesleeve\Stapler\Attachment $attachedFile
	 * @return void
	 */
	public function remove()
	{
		$directory = $this->findDirectory($this->attachedFile);
		$this->emptyDirectory($directory, true);
	}

	/**
	 * Utility function to return the base directory of the uploaded file for 
	 * a file attachment.
	 * 
	 * @return string               
	 */
	public function findDirectory()
	{
		$filePath = $this->attachedFile->path();
		$offset = $this->attachedFile->getOffset($filePath);
		
		return substr($filePath, 0, $offset);
	}

	/**
	 * Determine if a style directory needs to be built and if so create it.
	 *
	 * @param  string $styleName
	 * @return void
	 */
	public function buildDirectory($styleName)
	{
		$filePath = $this->attachedFile->path($styleName);

		if (!is_dir(dirname($filePath))) {
			mkdir(dirname($filePath), 0777, true);
		}
	}

	/**
	 * Determine if a style directory needs to be cleaned (emptied) and if so empty it.
	 *
	 * @param  string $styleName
	 * @return void
	 */
	public function cleanDirectory($styleName)
	{
		$filePath = $this->attachedFile->path($styleName);

		if (!$this->attachedFile->keep_old_files) {
			$fileDirectory = dirname($filePath);
			$this->emptyDirectory($fileDirectory);
		}
	}

	/**
	 * Recursively delete the files in a directory.
	 *
	 * @desc Recursively loops through each file in the directory and deletes it.
	 * @param string $directory
	 * @param boolean $deleteDirectory
	 * @return void
	 */
	public function emptyDirectory($directory, $deleteDirectory = false)
	{
		if (!$directoryHandle = opendir($directory)) {
			return;
		}
		
		while (false !== ($object = readdir($directoryHandle))) 
		{
			if ($object == '.' || $object == '..') {
				continue;
			}

			if (!is_dir($directory.'/'.$object)) {
				unlink($directory.'/'.$object);
			}
			else {
				$this->emptyDirectory($directory.'/'.$object, true);	// The object is a folder, recurse through it.
			}
		}
		
		if ($deleteDirectory)
		{
			closedir($directoryHandle);
			rmdir($directory);
		}
	}

	/**
	 * Move an uploaded file to it's intended destination
	 *
	 * @param  Symfony\Component\HttpFoundation\File\UploadedFile $file 
	 * @param  string $filePath 
	 * @return void 
	 */
	public function move($file, $filePath, $mode)
	{
		if ($file->isValid()) 
		{
            if (!move_uploaded_file($file->getPathname(), $filePath)) {
                $error = error_get_last();
                throw new Exceptions\FileException(sprintf('Could not move the file "%s" to "%s" (%s)', $file->getPathname(), $filePath, strip_tags($error['message'])));
            }

            $this->setPermissions($filePath, $mode);

            return $filePath;
        }

        throw new Exceptions\FileException($file->getErrorMessage($file->getError()));
	}

	/**
	 * Set the file permissions of a file upload
	 * Does not ignore umask.
	 * 
	 * @param string $filePath
	 * @param integer $mode
	 */
	public function setPermissions($filePath, $mode)
	{
		chmod($filePath, $mode & ~umask());
	}
}