<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Georg Ehrke <georg-nextcloud@ehrke.email>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\EndToEndEncryption;

use OCA\EndToEndEncryption\Connector\Sabre\RedirectRequestPlugin;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\ILogger;

/**
 * Class FileService
 *
 * @package OCA\EndToEndEncryption
 */
class FileService {

	/**
	 * FileService constructor.
	 *
	 * @param ILogger $logger
	 */
	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	/**
	 * @param Folder $folder
	 * @return bool Whether or not it changed any files
	 */
	public function revertChanges(Folder $folder): bool {
		$intermediateFiles = $this->getIntermediateFiles($folder);
		if (empty($intermediateFiles['to_save']) && empty($intermediateFiles['to_delete'])) {
			return false;
		}

		/** @var Node $intermediateFile */
		foreach ($intermediateFiles['to_save'] as $intermediateFile) {
			$intermediateFile->delete();
		}

		/** @var Node $intermediateFile */
		foreach ($intermediateFiles['to_delete'] as $intermediateFile) {
			$newPath = $this->removeSuffixFromString($intermediateFile->getPath(), RedirectRequestPlugin::DELETE_SUFFIX);
			$intermediateFile->move($newPath);
		}

		return true;
	}

	/**
	 * @param Folder $folder
	 * @return bool Whether or not it changed any files
	 */
	public function finalizeChanges(Folder $folder): bool {
		$intermediateFiles = $this->getIntermediateFiles($folder);
		if (empty($intermediateFiles['to_save']) && empty($intermediateFiles['to_delete'])) {
			return false;
		}

		/** @var Node $intermediateFile */
		foreach ($intermediateFiles['to_save'] as $intermediateFile) {
			$newPath = $this->removeSuffixFromString($intermediateFile->getPath(), RedirectRequestPlugin::SAVE_SUFFIX);
			$intermediateFile->move($newPath);
		}

		/** @var Node $intermediateFile */
		foreach ($intermediateFiles['to_delete'] as $intermediateFile) {
			$intermediateFile->delete();
		}

		return true;
	}

	/**
	 * @param Folder $folder
	 * @return array
	 */
	private function getIntermediateFiles(Folder $folder): array {
		$listing = $folder->getDirectoryListing();
		$result = [
			'to_save' => [],
			'to_delete' => [],
		];

		foreach ($listing as $node) {
			if ($this->isIntermediateFileToSave($node)) {
				$result['to_save'][] = $node;
				continue;
			}

			if ($this->isIntermediateFileToDelete($node)) {
				$result['to_delete'][] = $node;
			}
		}

		return $result;
	}

	/**
	 * @param Node $node
	 * @return bool
	 */
	private function isIntermediateFileToSave(Node $node): bool {
		return (substr($node->getName(), strlen(RedirectRequestPlugin::SAVE_SUFFIX) * -1) === RedirectRequestPlugin::SAVE_SUFFIX);
	}

	/**
	 * @param Node $node
	 * @return bool
	 */
	private function isIntermediateFileToDelete(Node $node): bool {
		return (substr($node->getName(), strlen(RedirectRequestPlugin::DELETE_SUFFIX) * -1) === RedirectRequestPlugin::DELETE_SUFFIX);
	}

	/**
	 * @param string $filename
	 * @param string $suffix
	 * @return string
	 */
	private function removeSuffixFromString(string $filename, string $suffix):string {
		// Do nothing if filename does not end with suffix
		if (substr($filename, strlen($suffix) * -1) !== $suffix) {
			return $filename;
		}

		return substr($filename, 0, strlen($filename) - strlen($suffix));
	}
}
