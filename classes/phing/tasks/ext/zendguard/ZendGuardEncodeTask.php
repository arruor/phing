<?php

/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
 */
use Phing\Exception\BuildException;
use Phing\Io\File;
use Phing\Io\IOException;
use Phing\Project;
use Phing\Type\FileSet;


/**
 * Encodes files using Zeng Guard Encoder
 *
 * @author    Petr Rybak <petr@rynawe.net>
 * @version   $Id$
 * @package   phing.tasks.ext.zendguard
 * @since     2.4.3
 */
class ZendGuardEncodeTask extends MatchingTask
{
    protected $filesets = array();
    protected $encodeCommand;

    /**
     * TASK PROPERTIES
     *
     * See http://static.zend.com/topics/Zend-Guard-User-Guidev5x.pdf
     * for more information on how to use ZendGuard
     *
     */
    /**
     * Permanently deletes (see warning below) the original source files specified in the
     * SourceInputPath and saves the encoded files in its place.
     * This option has no option parameter.
     * When this option is use, do not use the output_file parameter.
     *
     * Warning:
     * To avoid permanent loss of non-encoded scripts, make a backup. Deleted files
     * cannot be restored or recovered and will be permanently deleted with this option.
     * If you are unsure about deleting the source files, use the ––rename-source option
     * instead
     *
     * @var bool
     */
    protected $deleteSource = true;
    /**
     * Move the original source file to <input_file>.<renameSourceExt> and save the encoded file in its
     * place.
     *
     * If specified deleteSource will be automatically disabled.
     *
     * @var string
     */
    protected $renameSourceExt = null;
    /**
     * Turns short PHP tag (“<?” ) recognition either on or off.
     * On or off must be specified as an argument when using this option.
     * The default, when option is not used in the command-line, is - on
     *
     * @var bool
     */
    protected $shortTags = true;
    /**
     * Turn ASP tag (“<%” ) recognition on/off. (default: off). On or off must be specified
     * as an argument when using this option.
     * The default, when this option is not used in the command-line, is - off
     *
     * @var bool
     */
    protected $aspTags = false;
    /**
     *
     * Disables the PHP-compatible header that is added to the top of every encoded file
     * by default. Encoded files generated with this option will not display a meaningful
     * error when loaded by PHP that doesn't have the Zend Optimizer properly installed.
     * Using this option saves approximately 1.5KB for every encoded file. Do not use it
     * unless disk space constraints are critica
     *
     * @var bool
     */
    protected $noHeader = false;
    /**
     * If cryptography should be used to encode the source code
     *
     * @var bool
     */
    protected $useCrypto = false;
    /**
     * Force cooperation with other encoded files only. This option generates files that
     * work exclusively with associated encoded files. Associated encoded files are
     * those generated by the same company. Files that do not share the same encoded
     * company association cannot call these files
     *
     * @var bool
     */
    protected $encodedOnly = false;
    /**
     * Allow encoding previously encoded files. (NOT recommended!)
     *
     * @var bool
     */
    protected $forceEncode = false;
    /**
     * Make an encoded file to expire on the given date. Date is in yyyy-mm-dd format.
     *
     * @var string
     */
    protected $expires = null;
    /**
     * Level of obfuscation. Defaults to 0 (no obfuscation).
     *
     * @var int
     */
    protected $obfuscationLevel = 0;
    /**
     * Optimization mask. (default value: [+++++++])
     * opt_mask is an integer representing a bit-mask.
     * The default value enables all of the optimization passes.
     * Each optimization pass of the Zend Optimizer can be turned on or off based on
     * the mask entered
     *
     * @var int
     */
    protected $optMask = null;
    /**
     * Path to the zend encoder binary
     *
     * @var string
     */
    protected $zendEncoderPath = null;
    /**
     * Path to private key for licensing
     *
     * @var string
     */
    protected $privateKeyPath = null;
    /**
     * Enable licensing.
     * If enabled, productName must be defined.
     *
     * @var bool
     */
    protected $licenseProduct = false;
    /**
     * If true the ownership, permissions and timestamps
     * of the encoded files won't be preserved.
     *
     * @var bool
     */
    protected $ignoreFileModes = false;
    /**
     * Enable signing
     * If enabled, productName must be defined.
     *
     * @var bool
     */
    protected $signProduct = false;
    /**
     * Product name. Must be defined if licenseProduct
     * or signProduct is set to 1
     *
     * @var string
     */
    protected $productName = null;
    /**
     * Embed the information in the specified file into the header of the encoded file
     * (overrides noHeader)
     *
     * @var string
     */
    protected $prologFile = null;

    /**
     * TASK PROPERTIES SETTERS
     * @param $value
     */
    public function setZendEncoderPath($value)
    {
        $this->zendEncoderPath = $value;
    }

    /**
     * @param $value
     */
    public function setPrivateKeyPath($value)
    {
        $this->privateKeyPath = $value;
    }

    /**
     * @param $value
     */
    public function setShortTags($value)
    {
        $this->shortTags = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setAspTags($value)
    {
        $this->aspTags = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setDeleteSource($value)
    {
        $this->shortTags = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setUseCrypto($value)
    {
        $this->useCrypto = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setObfuscationLevel($value)
    {
        $this->obfuscationLevel = (int) $value;
    }

    /**
     * @param $value
     */
    public function setLicenseProduct($value)
    {
        $this->licenseProduct = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setPrologFile($value)
    {
        $this->prologFile = $value;
    }

    /**
     * @param $value
     */
    public function setSignProduct($value)
    {
        $this->signProduct = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setForceEncode($value)
    {
        $this->forceEncode = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setEncodedOnly($value)
    {
        $this->encodedOnly = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setIgnoreFileModes($value)
    {
        $this->ignoreFileModes = (bool) $value;
    }

    /**
     * @param $value
     */
    public function setExpires($value)
    {
        $this->expires = $value;
    }

    /**
     * @param $value
     */
    public function setProductName($value)
    {
        $this->productName = $value;
    }

    /**
     * @param $value
     */
    public function setOptMask($value)
    {
        $this->optMask = (int) $value;
    }

    /**
     * @param $value
     */
    public function setRenameSourceExt($value)
    {
        $this->renameSourceExt = $value;
    }

    /**
     * @param $value
     */
    public function setNoHeader($value)
    {
        $this->noHeader = (bool) $value;
    }

    /**
     * Add a new fileset.
     *
     * @return FileSet
     */
    public function createFileSet()
    {
        $this->fileset = new ZendGuardFileSet();
        $this->filesets[] = $this->fileset;

        return $this->fileset;
    }

    /**
     * Verifies that the configuration is correct
     *
     * @throws \Phing\Exception\BuildException
     */
    protected function verifyConfiguration()
    {
        // Check that the zend encoder path is specified
        if (empty($this->zendEncoderPath)) {
            throw new BuildException("Zend Encoder path must be specified");
        }

        // verify that the zend encoder binary exists
        if (!file_exists($this->zendEncoderPath)) {
            throw new BuildException("Zend Encoder not found on path " . $this->zendEncoderPath);
        }

        // if either sign or license is required the private key path needs to be defined
        // and the file has to exist and product name has to be specified
        if ($this->signProduct || $this->licenseProduct) {
            if (empty($this->privateKeyPath)) {
                throw new BuildException("Licensing or signing requested but privateKeyPath not provided.");
            }
            if (!is_readable($this->privateKeyPath)) {
                throw new BuildException("Licensing or signing requested but private key path doesn't exist or is unreadable.");
            }
            if (empty($this->productName)) {
                throw new BuildException("Licensing or signing requested but product name not provided.");
            }
        }

        // verify prolog file exists
        if (!empty($this->prologFile)) {
            if (!file_exists($this->prologFile)) {
                throw new BuildException("The prolog file doesn't exist: " . $this->prologFile);
            }
        }
    }

    /**
     * Do the work
     *
     * @throws \Phing\Exception\BuildException
     */
    public function main()
    {
        $this->verifyConfiguration();
        $this->prepareEncoderCommand();

        try {
            if (empty($this->filesets)) {
                throw new BuildException("You must supply nested fileset.",
                    $this->getLocation());
            }

            $encodedFilesCounter = 0;

            foreach ($this->filesets as $fs) {
                /* @var $fs FileSet */

                /* @var $fsBasedir File */
                $fsBasedir = $fs->getDir($this->project)->getAbsolutePath();

                $files = $fs->getFiles($this->project, false);

                foreach ($files as $file) {
                    $f = new File($fsBasedir, $file);

                    if ($f->isFile()) {
                        $path = $f->getAbsolutePath();

                        $this->log("Encoding " . $path, Project::MSG_VERBOSE);
                        $this->encodeFile($path);

                        $encodedFilesCounter++;
                    }
                }
            }

            $this->log("Encoded files: " . $encodedFilesCounter);
        } catch (IOException $ioe) {
            $msg = "Problem encoding files: " . $ioe->getMessage();
            throw new BuildException($msg, $ioe, $this->getLocation());
        }
    }

    /**
     * Prepares the main part of the command that will be
     * used to encode the given file(s).
     */
    protected function prepareEncoderCommand()
    {
        $command = $this->zendEncoderPath . " ";

        if (!empty($this->renameSourceExt)) {
            $command .= " --rename-source " . $this->renameSourceExt . " ";
        } elseif ($this->deleteSource) {
            // delete source
            $command .= " --delete-source ";
        }

        // short tags
        $command .= " --short-tags " . (($this->shortTags) ? 'on' : 'off') . " ";

        // asp tags
        $command .= " --asp-tags " . (($this->aspTags) ? 'on' : 'off') . " ";

        // use crypto
        if ($this->useCrypto) {
            $command .= " --use-crypto ";
        }

        // ignore file modes
        if ($this->ignoreFileModes) {
            $command .= " --ignore-file-modes ";
        }

        // force encode
        if ($this->forceEncode) {
            $command .= " --force-encode ";
        }

        // expires
        if (!empty($this->expires)) {
            $command .= " --expires " . $this->expires . " ";
        }

        // insert prolog file name or no-header
        if (!empty($this->prologFile)) {
            $command .= " --prolog-filename " . $this->prologFile . " ";
        } elseif ($this->noHeader) {
            // no-header
            $command .= " --no-header ";
        }

        // obfuscation level
        if ($this->obfuscationLevel > 0) {
            $command .= " --obfuscation-level " . $this->obfuscationLevel . " ";
        }

        // encoded only
        if ($this->encodedOnly) {
            $command .= " --encoded-only ";
        }

        // opt mask
        if (null !== $this->optMask) {
            $command .= " --optimizations " . $this->optMask . " ";
        }

        // Signing or licensing
        if ($this->signProduct) {
            $command .= " --sign-product " . $this->productName . " --private-key " . $this->privateKeyPath . " ";
        } elseif ($this->licenseProduct) {
            $command .= " --license-product " . $this->productName . " --private-key " . $this->privateKeyPath . " ";
        }

        // add a blank space
        $command .= " ";

        $this->encodeCommand = $command;

    }

    /**
     * Encodes a file using currently defined Zend Guard settings
     *
     * @param string $filePath Path to the encoded file
     * @throws \Phing\Exception\BuildException
     * @return bool
     */
    protected function encodeFile($filePath)
    {
        $command = $this->encodeCommand . $filePath . ' 2>&1';

        $this->log('Running: ' . $command, Project::MSG_VERBOSE);

        $tmp = exec($command, $output, $return_var);
        if ($return_var !== 0) {
            throw new BuildException("Encoding failed. \n Msg: " . $tmp . " \n Encode command: " . $command);
        }

        return true;
    }

}

/**
 * This is a FileSet with the to specify permissions.
 *
 * Permissions are currently not implemented by PEAR Archive_Tar,
 * but hopefully they will be in the future.
 *
 * @package phing.tasks.ext.zendguard
 */
class ZendGuardFileSet extends FileSet
{
    private $files = null;

    /**
     *  Get a list of files and directories specified in the fileset.
     * @param Project $p
     * @param bool $includeEmpty
     * @throws \Phing\Exception\BuildException
     * @return array a list of file and directory names, relative to
     *               the baseDir for the project.
     */
    public function getFiles(Project $p, $includeEmpty = true)
    {

        if ($this->files === null) {

            $ds = $this->getDirectoryScanner($p);
            $this->files = $ds->getIncludedFiles();
        } // if ($this->files===null)

        return $this->files;
    }

}
