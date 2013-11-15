<?php

class simpleCrawler {
    private $url;
    private $scheme;
    private $domain;
    private $path;
    private $file;
    
    private $crawled;
    private $seen;  
    private $toCrawl;
    
    private $debug  = false;
    private $debug2 = false;

    public function __construct($url) {
        $parts = parse_url($url);
        if (!isset($parts['scheme'])) throw new Exception("URL must contain (http://)");
        if (empty($parts['scheme'])) throw new Exception("URL must contain (http://)");
        if (!isset($parts['host'])) throw new Exception("URL must contain (example.com)");
        if (empty($parts['host'])) throw new Exception("URL must contain (example.com)");
        $this->scheme = strtolower($parts['scheme']);
        $this->domain = strtolower($parts['host']);
        $root = $parts['path'];
        $this->path = pathinfo($root, PATHINFO_DIRNAME);
        $this->file = pathinfo($root, PATHINFO_BASENAME);
        $this->url = $url;
        if ($this->path == '\\') $this->path = '/';
        
        $this->toCrawl = array($url);
        $this->crawled = array();
        $this->seen    = array();
        
        while(!empty($this->toCrawl)) {
            foreach($this->toCrawl as $key=>$value) {
                $this->crawl($value);
                $this->seen[] = $value;
                unset($this->toCrawl[$key]);
            }
        }    
    }
    
    private function crawl($url) {
        if ($this->debug) echo "<i>Crawling: $url</i><br />";
        
        $links = $this->checkForLinks($url);
        if ($links === false) return;
        
        $pages = array();
        foreach($links as $link) {
            
            $parts = parse_url($link);
            
            if ($this->debug2) echo "<b>Testing: $link</b><br />";
            
            if (!isset($parts['path']) || empty($parts['path'])) continue;
            
            if (isset($parts['scheme'])) {
                $scheme = strtolower($parts['scheme']);
                if ($scheme != $this->scheme) continue;
            }
            
            if (isset($parts['host'])) {
                $domain = strtolower($parts['host']);
                if ($domain != $this->domain) continue;
            }
            
            $path = $parts['path'];
            if ($path[0] != '/')
                $path = $this->path .'/'. $path;
            
            $isDir = (substr($path, -1) == '/');
            $path = explode('/', $path);
            $level = 0;
            $new = array();
            foreach($path as $part) {

                if ($part == '.' || $part == '') continue;
                
                if ($part == '..') {
                    $level--;
                    if ($level < 0) break;
                } else {
                    $new[$level] = $part;
                    $level++;
                }
            }

            if ($level < 1) continue;
            
            $parsed = $this->scheme.'://'.$this->domain;
            
            for ($i=0; $i<$level; $i++) $parsed .= '/'.$new[$i];
            if ($isDir) $parsed .= '/';
                
            if ($this->debug2) echo $parsed . "<br />";
            
            if (!(in_array($parsed, $this->seen)
               || in_array($parsed, $this->toCrawl))) $this->toCrawl[] = $parsed;                
        }
        
        $this->crawled[] = $url;
    }
        
    private function checkForLinks($url) {
        if (substr($url, 0, 7) != 'http://') $url = 'http://'.$url;
        if (substr($url, -1) == '/') $url = substr($url, 0, -1);
        
        if ($url == 'http://localhost') $url = 'http://127.0.0.1';
    
        @$cnt = file_get_contents($url);
        if ($cnt === false) return false;
        
        preg_match_all("/<a [^>]*href[\s]*=[\s]*\"([^\"]*)\"/i", $cnt, $links);
        return $links[1];
    }
    
    public function getPages() {
        return $this->crawled;
    }
    
    public function getSiteMap() {
        ob_start();
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach($this->crawled as $url)
           echo "\t<url><loc>$url</loc></url>\n";
        echo "</urlset>";
        return ob_get_clean();
    }
    
}

$site = new Crawler('http://www.example.com/index.php');
echo $site->getSiteMap();

?> 
