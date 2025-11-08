<?php
// Version simplifiée de FPDF pour générer des PDF basiques
// Source: http://www.fpdf.org (License: permissive)

class FPDF {
    protected $page = 0;
    protected $n = 2;
    protected $buffer = '';
    protected $pages = [];
    protected $state = 0;
    protected $fonts = [];
    protected $FontFiles = [];
    protected $diffs = [];
    protected $images = [];
    protected $PageLinks = [];
    protected $links = [];
    protected $FontFamily = '';
    protected $FontStyle = '';
    protected $FontSizePt = 12;
    protected $underline = false;
    protected $DrawColor = '0 G';
    protected $FillColor = '0 g';
    protected $TextColor = '0 g';
    protected $ColorFlag = false;
    protected $ws = 0;
    protected $k;
    protected $x, $y;
    protected $lasth = 0;
    protected $LineWidth;
    protected $CoreFonts = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];
    protected $fontpath;

    function __construct($orientation='P', $unit='mm', $size='A4') {
        $this->k = ($unit=='pt') ? 1 : (($unit=='mm') ? 72/25.4 : (($unit=='cm') ? 72/2.54 : 72));
        $size = $this->_getpagesize($size);
        $this->DefPageSize = $size;
        $this->CurPageSize = $size;
        $this->wPt = $size[0];
        $this->hPt = $size[1];
        $this->w = $size[0]/$this->k;
        $this->h = $size[1]/$this->k;
        $this->DefOrientation = ($orientation=='L' ? 'L' : 'P');
        $this->CurOrientation = $this->DefOrientation;
        $this->PageBreakTrigger = $this->h-2*10;
        $this->LineWidth = .567/$this->k;
        $this->SetMargins(10,10);
        $this->SetAutoPageBreak(true,10);
        $this->SetDisplayMode('default');
    }

    function SetMargins($left, $top, $right=null) {
        $this->lMargin = $left;
        $this->tMargin = $top;
        if($right===null) $right = $left;
        $this->rMargin = $right;
    }

    function SetAutoPageBreak($auto, $margin=0) {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h-$margin;
    }

    function SetDisplayMode($zoom, $layout='default') {
        $this->ZoomMode = $zoom;
        $this->LayoutMode = $layout;
    }

    function AddPage($orientation='', $size='') {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
    }

    function SetFont($family, $style='', $size=0) {
        $family = strtolower($family);
        if($size==0) $size = $this->FontSizePt;
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
    }

    function SetTextColor($r, $g=null, $b=null) {
        if($g===null) $this->TextColor = sprintf('%.3F g',$r/255);
        else $this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
        $this->ColorFlag = ($this->FillColor!=$this->TextColor);
    }

    function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
        $k = $this->k;
        if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AutoPageBreak) {
            $this->AddPage($this->CurOrientation,$this->CurPageSize);
            $this->y = $this->tMargin;
        }
        $s = '';
        if($fill || $border==1) {
            if($fill) $op = ($border==1) ? 'B' : 'f';
            else $op = 'S';
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
        }
        if(is_string($border)) {
            $x = $this->x;
            $y = $this->y;
            if(strpos($border,'L')!==false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
            if(strpos($border,'T')!==false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
            if(strpos($border,'R')!==false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
            if(strpos($border,'B')!==false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
        }
        if($txt!=='') {
            if($align=='R') $dx = $w-$this->cMargin-$this->GetStringWidth($txt);
            elseif($align=='C') $dx = ($w-$this->GetStringWidth($txt))/2;
            else $dx = $this->cMargin;
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$this->_escape($txt));
        }
        if($s) $this->_out($s);
        $this->lasth = $h;
        if($ln>0) {
            $this->y += $h;
            if($ln==1) $this->x = $this->lMargin;
        } else $this->x += $w;
    }

    function Ln($h=null) {
        $this->x = $this->lMargin;
        if($h===null) $this->y += $this->lasth;
        else $this->y += $h;
    }

    function GetStringWidth($s) {
        return strlen($s)*$this->FontSize*0.5;
    }

    function Output($dest='', $name='', $isUTF8=false) {
        if($this->state<3) $this->Close();
        if($dest=='') {
            $dest = 'I';
            $name = 'doc.pdf';
        }
        if($dest=='I') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.$name.'"');
            echo $this->buffer;
        } elseif($dest=='D') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.$name.'"');
            echo $this->buffer;
        } elseif($dest=='F') {
            $f = fopen($name,'wb');
            fwrite($f,$this->buffer);
            fclose($f);
        } elseif($dest=='S') {
            return $this->buffer;
        }
    }

    protected function _getpagesize($size) {
        $a = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28), 'letter'=>array(612,792), 'legal'=>array(612,1008));
        return $a[strtolower($size)];
    }

    protected function _out($s) {
        if($this->state==2) $this->pages[$this->page] .= $s."\n";
        else $this->buffer .= $s."\n";
    }

    protected function _escape($s) {
        return strtr($s,array(')'=>'\\)','('=>'\\(','\\'=>'\\\\'));
    }

    protected function Close() {
        if($this->state==3) return;
        $this->state = 3;
        $this->buffer = "%PDF-1.3\n";
        $this->_putpages();
        $this->_putresources();
        $this->_putinfo();
        $this->_putcatalog();
        $o = strlen($this->buffer);
        $this->_out('xref');
        $this->_out('0 '.($this->n+1));
        $this->_out('0000000000 65535 f ');
        for($i=1;$i<=$this->n;$i++) $this->_out(sprintf('%010d 00000 n ',$this->offsets[$i]));
        $this->_out('trailer');
        $this->_out('<<');
        $this->_putdict(array('/Size'=>$this->n+1,'/Root'=>$this->n.' 0 R','/Info'=>($this->n-1).' 0 R'));
        $this->_out('>>');
        $this->_out('startxref');
        $this->_out($o);
        $this->_out('%%EOF');
    }

    protected function _beginpage($orientation, $size) {}
    protected function _endpage() {}

    protected function _newobj() {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->_out($this->n.' 0 obj');
    }

    protected function _putpages() {
        $nb = $this->page;
        for($n=1;$n<=$nb;$n++) $this->PageLinks[$n] = array();
        for($n=1;$n<=$nb;$n++) {
            $this->_newobj();
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');
            $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->wPt,$this->hPt));
            $this->_out('/Contents '.($this->n+1).' 0 R>>');
            $this->_out('endobj');
            $this->_newobj();
            $p = $this->pages[$n];
            $this->_out('<</Length '.strlen($p).'>>');
            $this->_out('stream');
            $this->_out($p);
            $this->_out('endstream');
            $this->_out('endobj');
        }
        $this->offsets[1] = strlen($this->buffer);
        $this->_out('1 0 obj');
        $this->_out('<</Type /Pages');
        $kids = '/Kids [';
        for($i=0;$i<$nb;$i++) $kids .= (3+2*$i).' 0 R ';
        $this->_out($kids.']');
        $this->_out('/Count '.$nb);
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putresources() {
        $this->_newobj();
        $this->_out('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putinfo() {
        $this->_newobj();
        $this->_out('<<');
        $this->_putdict(array('/Producer'=>'FPDF','Creator'=>'ArchiMeuble'));
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putcatalog() {
        $this->_newobj();
        $this->_out('<<');
        $this->_putdict(array('/Type'=>'/Catalog','/Pages'=>'1 0 R'));
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putdict($d) {
        foreach($d as $k=>$v) $this->_out($k.' '.$v);
    }
}
