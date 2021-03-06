<?php
/**
 * PHP VCS wrapper Git Cli file wrapper
 *
 * This file is part of vcs-wrapper.
 *
 * vcs-wrapper is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Lesser General Public License as published by the Free
 * Software Foundation; version 3 of the License.
 *
 * vcs-wrapper is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with vcs-wrapper; if not, write to the Free Software Foundation, Inc., 51
 * Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package VCSWrapper
 * @subpackage GitCliWrapper
 * @version $Revision$
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
 */

namespace Arbit\VCSWrapper\GitCli;

/**
 * File implementation vor Git Cli wrapper
 *
 * @package VCSWrapper
 * @subpackage GitCliWrapper
 * @version $Revision$
 */
class File extends \Arbit\VCSWrapper\GitCli\Resource implements \Arbit\VCSWrapper\File, \Arbit\VCSWrapper\Blameable, \Arbit\VCSWrapper\Diffable
{
    /**
     * Regular expression used to extract data from a git blame line.
     */
    const BLAME_REGEXP = '{^\^?(?P<version>[0-9a-f]{1,40})[^(]+\((?P<author>.*)\s+(?P<date>(?:19|20).*)\s+(?P<number>\d+)\)(?: (?P<line>.*))?}';

    /**
     * Get file contents
     *
     * Get the contents of the current file.
     *
     * @return string
     */
    public function getContents()
    {
        return file_get_contents($this->root . $this->path);
    }

    /**
     * Get mime type
     *
     * Get the mime type of the current file. If this information is not
     * available, just return 'application/octet-stream'.
     *
     * @return string
     */
    public function getMimeType()
    {
        // If not set, fall back to application/octet-stream
        return 'application/octet-stream';
    }

    /**
     * Get blame information for resource
     *
     * The method should return author and revision information for each line,
     * describing who when last changed the current resource. The returned
     * array should look like:

     * <code>
     *  array(
     *      T_LINE_NUMBER => array(
     *          'author'  => T_STRING,
     *          'version' => T_STRING,
     *      ),
     *      ...
     *  );
     * </code>
     *
     * If some file in the repository has no blame information associated, like
     * binary files, the method should return false.
     *
     * Optionally a version may be specified which defines a later version of
     * the resource for which the blame information should be returned.
     *
     * @param mixed $version
     * @return mixed
     */
    public function blame($version = null)
    {
        $version = ($version === null) ? $this->getVersionString() : $version;

        if (!in_array($version, $this->getVersions(), true)) {
            throw new \UnexpectedValueException("Invalid log entry $version for {$this->path}.");
        }

        if (($blame = \Arbit\VCSWrapper\Cache\Manager::get($this->path, $version, 'blame')) === false) {
            // Refetch the basic blamermation, and cache it.
            $process = new \Arbit\VCSWrapper\GitCli\Process();
            $process->workingDirectory($this->root);

            // Execute command
            $return = $process->argument('blame')->argument('-l')->argument(new \SystemProcess\Argument\PathArgument('.' . $this->path))->execute();
            $contents = preg_split('(\r\n|\r|\n)', trim($process->stdoutOutput));

            // Convert returned lines into diff structures
            $blame = array();
            foreach ($contents as $nr => $line) {
                if (preg_match(self::BLAME_REGEXP, $line, $match)) {
                    $match['line'] = isset($match['line']) ? $match['line'] : null;
                    $blame[] = new \Arbit\VCSWrapper\Blame($match['line'], $match['version'], $match['author'], strtotime($match['date']));
                } else {
                    throw new \RuntimeException("Could not parse line: $line");
                }
            }

            \Arbit\VCSWrapper\Cache\Manager::cache($this->path, $version, 'blame', $blame);
        }

        return $blame;
    }

    /**
     * Get diff
     *
     * Get the diff between the current version and the given version.
     * Optionally you may specify another version then the current one as the
     * diff base as the second parameter.
     *
     * @param string $version
     * @param string $current
     * @return \Arbit\VCSWrapper\Resource
     */
    public function getDiff($version, $current = null)
    {
        if (!in_array($version, $this->getVersions(), true)) {
            throw new \UnexpectedValueException("Invalid log entry $version for {$this->path}.");
        }

        $current = ($current === null) ? $this->getVersionString() : $current;

        if (($diff = \Arbit\VCSWrapper\Cache\Manager::get($this->path, $version, 'diff')) === false) {
            // Refetch the basic content information, and cache it.
            $process = new \Arbit\VCSWrapper\GitCli\Process();
            $process->workingDirectory($this->root);
            $process->argument('diff')->argument('--no-ext-diff');
            $process->argument($version . '..' . $current)->argument(new \SystemProcess\Argument\PathArgument('.' . $this->path))->execute();

            // Parse resulting unified diff
            $parser = new \Arbit\VCSWrapper\Diff\Unified();
            $diff   = $parser->parseString($process->stdoutOutput);
            \Arbit\VCSWrapper\Cache\Manager::cache($this->path, $version, 'diff', $diff);
        }

        return $diff;
    }
}
