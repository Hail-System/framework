<?php

namespace Hail\Filesystem\Adapter;

use Hail\Filesystem\Adapter\Polyfill\StreamedWritingTrait;
use Hail\Filesystem\AdapterInterface;
use Hail\Filesystem\Util;

/**
 * An adapter that keeps the filesystem in memory.
 */
class Memory implements AdapterInterface
{
	use StreamedWritingTrait;

	/**
	 * The emulated filesystem.
	 *
	 * Start with the root directory initialized.
	 *
	 * @var array
	 */
	protected $storage = ['' => ['type' => 'dir']];

	/**
	 * @inheritdoc
	 */
	public function copy($path, $newpath)
	{
		// Make sure all the destination sub-directories exist.
		if (!$this->doCreateDir(Util::dirname($newpath))) {
			return false;
		}

		$this->storage[$newpath] = $this->storage[$path];

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function createDir($dirname, array $config)
	{
		if (!$this->doCreateDir($dirname)) {
			return false;
		}

		return $this->getMetadata($dirname);
	}

	/**
	 * @inheritdoc
	 */
	public function delete($path)
	{
		if (!$this->hasFile($path)) {
			return false;
		}

		unset($this->storage[$path]);

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function deleteDir($dirname)
	{
		if (!$this->hasDirectory($dirname)) {
			return false;
		}

		foreach ($this->doListContents($dirname, true) as $path) {
			unset($this->storage[$path]);
		}

		unset($this->storage[$dirname]);

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function getMetadata($path)
	{
		$metadata = $this->storage[$path] + ['path' => $path];
		unset($metadata['contents']);

		return $metadata;
	}

	/**
	 * @inheritdoc
	 */
	public function getMimetype($path)
	{
		$mimetype = Util::guessMimeType($path, $this->storage[$path]['contents']);

		return [
			'mimetype' => $mimetype,
			'path' => $path,
		];
	}

	/**
	 * @inheritdoc
	 */
	public function getSize($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritdoc
	 */
	public function getTimestamp($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritdoc
	 */
	public function getVisibility($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritdoc
	 */
	public function has($path)
	{
		return isset($this->storage[$path]);
	}

	/**
	 * @inheritdoc
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$contents = $this->doListContents($directory, $recursive);

		return \array_map([$this, 'getMetadata'], \array_values($contents));
	}

	/**
	 * @inheritdoc
	 */
	public function read($path)
	{
		return [
			'path' => $path,
			'contents' => $this->storage[$path]['contents'],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function readStream($path)
	{
		$stream = \fopen('php://memory', 'w+b');

		\fwrite($stream, $this->storage[$path]['contents']);
		\rewind($stream);

		return \compact('path', 'stream');
	}

	/**
	 * @inheritdoc
	 */
	public function rename($path, $newpath)
	{
		if (!$this->copy($path, $newpath)) {
			return false;
		}
		unset($this->storage[$path]);

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function setVisibility($path, $visibility)
	{
		if (!$this->hasFile($path)) {
			return false;
		}

		$this->storage[$path]['visibility'] = $visibility;

		return $this->getVisibility($path);
	}

	/**
	 * @inheritdoc
	 */
	public function update($path, $contents, array $config)
	{
		if (!$this->hasFile($path)) {
			return false;
		}

		$this->storage[$path]['contents'] = $contents;
		$this->storage[$path]['timestamp'] = $config['timestamp'] ?? time();
		$this->storage[$path]['size'] = Util::contentSize($contents);
		$this->storage[$path]['visibility'] = $config['visibility'] ?? $this->storage[$path]['visibility'];

		return $this->getMetadata($path);
	}

	/**
	 * @inheritdoc
	 */
	public function write($path, $contents, array $config)
	{
		// Make sure all the destination sub-directories exist.
		if (!$this->doCreateDir(Util::dirname($path))) {
			return false;
		}

		$this->storage[$path]['type'] = 'file';
		$this->storage[$path]['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;

		return $this->update($path, $contents, $config);
	}

	/**
	 * Creates a directory.
	 *
	 * @param string $dirname
	 *
	 * @return bool
	 */
	protected function doCreateDir($dirname)
	{
		if ($this->hasDirectory($dirname)) {
			return true;
		}

		if ($this->hasFile($dirname)) {
			return false;
		}

		// Make sure all the sub-directories exist.
		if (!$this->doCreateDir(Util::dirname($dirname))) {
			return false;
		}

		$this->storage[$dirname]['type'] = 'dir';

		return true;
	}

	/**
	 * Filters the file system returning paths inside the directory.
	 *
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return string[]
	 */
	protected function doListContents($directory, $recursive)
	{
		$filter = function ($path) use ($directory, $recursive) {
			// Remove the root directory from any listing.
			if ($path === '') {
				return false;
			}

			if (Util::dirname($path) === $directory) {
				return true;
			}

			return $recursive && $this->pathIsInDirectory($path, $directory);
		};

		return \array_filter(\array_keys($this->storage), $filter);
	}

	/**
	 * Checks whether a directory exists.
	 *
	 * @param string $path The directory.
	 *
	 * @return bool True if it exists, and is a directory, false if not.
	 */
	protected function hasDirectory($path)
	{
		return $this->has($path) && $this->storage[$path]['type'] === 'dir';
	}

	/**
	 * Checks whether a file exists.
	 *
	 * @param string $path The file.
	 *
	 * @return bool True if it exists, and is a file, false if not.
	 */
	protected function hasFile($path)
	{
		return $this->has($path) && $this->storage[$path]['type'] === 'file';
	}

	/**
	 * Determines if the path is inside the directory.
	 *
	 * @param string $path
	 * @param string $directory
	 *
	 * @return bool
	 */
	protected function pathIsInDirectory($path, $directory)
	{
		return $directory === '' || \strpos($path, $directory . '/') === 0;
	}
}

