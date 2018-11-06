<?php
namespace think\console\output\driver;

use think\console\Output;
use think\facade\Env;

/**
 * 文件输出驱动
 */
class File
{
    protected $name = null;
    protected $path = null;

    protected $append = true;

    public function __construct(Output $output)
    {
        $this->path = Env::get('root_path') . 'database' . DIRECTORY_SEPARATOR . 'sqls';
        $this->name = time();
    }

    public function write($messages, $newline = false, $options = Output::OUTPUT_NORMAL)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
        $file = $this->getFile();

        if ($newline) {
            $messages .= PHP_EOL;
        }
        if ($this->append) {
            file_put_contents($file, $messages, FILE_APPEND);
        } else {
            file_put_contents($file, $messages);
            $this->append = true;
        }
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    public function getFile()
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->name . '.sql';
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function newFile()
    {
        $this->append = false;
        return $this;
    }
}
