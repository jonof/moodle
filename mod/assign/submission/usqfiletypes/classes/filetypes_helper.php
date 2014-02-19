<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * File types helper
 *
 * @package   assignsubmission_usqfiletypes
 * @copyright 2013 The University of Southern Queensland {@link http://www.usq.edu.au}
 * @author    Jonathon Fowler <fowlerj@usq.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_usqfiletypes;

class filetypes_helper {
    private static $typesets = null;

    public function prepare_file_type($extensions, $description) {
        $type = new \stdClass();
        $type->extensions = $extensions;
        $type->description = $description;
        return $type;
    }

    public function default_types() {
        if (empty(self::$typesets)) {
            self::$typesets = array(
                $this->prepare_file_type('doc,docx,rtf',                     'Office Documents (doc, docx, rtf)'),
                $this->prepare_file_type('ppt,pptx',                         'Office Presentations (ppt, pptx)'),
                $this->prepare_file_type('xls,xlsx',                         'Office Spreadsheets (xls, xlsx)'),
                $this->prepare_file_type('accdb,mdb',                        'Office Databases (mdb, accdb)'),
                $this->prepare_file_type('pdf',                              'PDFs (pdf)'),
                $this->prepare_file_type('rar,zip',                          'Archives (zip, rar)'),
                $this->prepare_file_type('avi,flv,mov,mp4,mpeg,mpg',         'Video (mpg, mp4, flv, mov, avi)'),
                $this->prepare_file_type('aac,aif,aiff,m4a,mp2,mp3,wav,wma', 'Audio (mp3, mp2, aac, m4a, wma, wav, aif)'),
                $this->prepare_file_type('bmp,gif,jpeg,jpg,png,tif,tiff',    'Images (jpg, png, gif, tif, bmp)'),
                $this->prepare_file_type('odt,txt',                          'Other documents (odt, txt)'),
                $this->prepare_file_type('odp',                              'Other presentations (odp)'),
                $this->prepare_file_type('ods',                              'Other spreadsheets (ods)'),
                $this->prepare_file_type('odb',                              'Other databases (odb)'),
                $this->prepare_file_type('tar,tar.bz2,tar.gz,tbz2,tgz',      'Other archives (tar, tar.gz, tar.bz2)'),
                $this->prepare_file_type('mkv,ogg,ogv',                      'Other video (mkv, ogv, ogg)'),
                $this->prepare_file_type('flac,oga,ogg,spx',                 'Other audio (ogg, oga, flac, spx)'),
            );
        }
        return self::$typesets;
    }
}
