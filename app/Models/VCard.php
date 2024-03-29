<?php

namespace App\Models;

use Behat\Transliterator\Transliterator;
use App\Models\VCardException;

class VCard
{
    /**
     * definedElements
     *
     * @var array
     */
    private $definedElements;

    /**
     * Filename
     *
     * @var string
     */
    private $filename;

    /**
     * Save Path
     *
     * @var string
     */
    private $savePath = null;

    /**
     * Multiple properties for element allowed
     *
     * @var array
     */
    private $multiplePropertiesForElementAllowed = [
        'email',
        'address',
        'phoneNumber',
        'url',
        'label'
    ];

    /**
     * Properties
     *
     * @var array
     */
    private $properties;

    /**
     * Default Charset
     *
     * @var string
     */
    public $charset = 'utf-8';

    /**
     * @param string $name
     * @param string $extended
     * @param string $street
     * @param string $city
     * @param string $region
     * @param string $zip
     * @param string $country
     * @param string $type
     * @return $this
     * @throws VCardException
     */
    public function addAddress(
        string $name = '',
        string $extended = '',
        string $street = '',
        string $city = '',
        string $region = '',
        string $zip = '',
        string $country = '',
        string $type = 'WORK;POSTAL'
    ): VCard
    {
        // init value
        $value = $name . ';' . $extended . ';' . $street . ';' . $city . ';' . $region . ';' . $zip . ';' . $country;

        // set property
        $this->setProperty(
            'address',
            'ADR' . (($type !== '') ? ';' . $type : '') . $this->getCharsetString(),
            $value
        );

        return $this;
    }

    /**
     * @param $date
     * @return $this
     * @throws VCardException
     */
    public function addBirthday($date): VCard
    {
        $this->setProperty(
            'birthday',
            'BDAY',
            $date
        );

        return $this;
    }

    /**
     * @param $company
     * @param string $department
     * @return $this
     * @throws VCardException
     */
    public function addCompany($company, string $department = ''): VCard
    {
        $this->setProperty(
            'company',
            'ORG' . $this->getCharsetString(),
            $company
            . ($department !== '' ? ';' . $department : '')
        );

        // if filename is empty, add to filename
        if ($this->filename === null) {
            $this->setFilename($company);
        }

        return $this;
    }

    /**
     * @param $address
     * @param string $type
     * @return $this
     * @throws VCardException
     */
    public function addEmail($address, string $type = ''): VCard
    {
        $this->setProperty(
            'email',
            'EMAIL;INTERNET' . (($type !== '') ? ';' . $type : ''),
            $address
        );

        return $this;
    }

    /**
     * @param $jobtitle
     * @return $this
     * @throws VCardException
     */
    public function addJobtitle($jobtitle): VCard
    {
        $this->setProperty(
            'jobtitle',
            'TITLE' . $this->getCharsetString(),
            $jobtitle
        );

        return $this;
    }

    /**
     * @param $label
     * @param string $type
     * @return $this
     * @throws VCardException
     */
    public function addLabel($label, string $type = ''): VCard
    {
        $this->setProperty(
            'label',
            'LABEL' . ($type !== '' ? ';' . $type : ''),
            $label
        );

        return $this;
    }

    /**
     * @param $role
     * @return $this
     * @throws VCardException
     */
    public function addRole($role): VCard
    {
        $this->setProperty(
            'role',
            'ROLE' . $this->getCharsetString(),
            $role
        );

        return $this;
    }

    /**
     * @param $property
     * @param $url
     * @param bool $include
     * @param $element
     * @throws VCardException
     */
    private function addMedia($property, $url, $include = true, $element): void
    {
        $mimeType = null;

        //Is this URL for a remote resource?
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $headers = get_headers($url, 1);

            if (array_key_exists('Content-Type', $headers)) {
                $mimeType = $headers['Content-Type'];
                if (is_array($mimeType)) {
                    $mimeType = end($mimeType);
                }
            }
        } else {
            //Local file, so inspect it directly
            $mimeType = mime_content_type($url);
        }
        if (strpos($mimeType, ';') !== false) {
            $mimeType = strstr($mimeType, ';', true);
        }
        if (!is_string($mimeType) || strpos($mimeType, 'image/') !== 0) {
            throw VCardException::invalidImage();
        }
        $fileType = strtoupper(substr($mimeType, 6));

        if ($include) {
            if ((bool)ini_get('allow_url_fopen') === true) {
                $value = file_get_contents($url);
            } else {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $value = curl_exec($curl);
                curl_close($curl);
            }

            if (!$value) {
                throw VCardException::emptyURL();
            }

            $value = base64_encode($value);
            $property .= ";ENCODING=b;TYPE=" . $fileType;
        } else {
            if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $propertySuffix = ';VALUE=URL';
                $propertySuffix .= ';TYPE=' . strtoupper($fileType);

                $property .= $propertySuffix;
            }
            $value = $url;
        }

        $this->setProperty(
            $element,
            $property,
            $value
        );
    }

    /**
     * @param $property
     * @param $content
     * @param $element
     * @throws VCardException
     */
    private function addMediaContent($property, $content, $element): void
    {
        $finfo = new \finfo();
        $mimeType = $finfo->buffer($content, FILEINFO_MIME_TYPE);

        if (strpos($mimeType, ';') !== false) {
            $mimeType = strstr($mimeType, ';', true);
        }
        if (!is_string($mimeType) || strpos($mimeType, 'image/') !== 0) {
            throw VCardException::invalidImage();
        }
        $fileType = strtoupper(substr($mimeType, 6));

        $content = base64_encode($content);
        $property .= ";ENCODING=b;TYPE=" . $fileType;

        $this->setProperty(
            $element,
            $property,
            $content
        );
    }

    /**
     * @param string $lastName
     * @param string $firstName
     * @param string $additional
     * @param string $prefix
     * @param string $suffix
     * @return $this
     * @throws VCardException
     */
    public function addName(
        string $lastName = '',
        string $firstName = '',
        string $middleName = '',
        string $additional = '',
        string $prefix = '',
        string $suffix = ''
    ): VCard
    {
        // define values with non-empty values
        $values = array_filter([
            $prefix,
            $firstName,
            $additional,
            $lastName,
            $suffix,
        ]);

        // define filename
        $this->setFilename($values);

        // set property
        $property = $lastName . ';' . $firstName . ';' . $middleName . ';' . $additional . ';' . $prefix . ';' . $suffix;
        $this->setProperty(
            'name',
            'N' . $this->getCharsetString(),
            $property
        );

        // is property FN set?
        if (!$this->hasProperty('FN')) {
            // set property
            $this->setProperty(
                'fullname',
                'FN' . $this->getCharsetString(),
                trim(implode(' ', $values))
            );
        }

        return $this;
    }

    /**
     * @param $note
     * @return $this
     * @throws VCardException
     */
    public function addNote($note): VCard
    {
        $this->setProperty(
            'note',
            'NOTE' . $this->getCharsetString(),
            $note
        );

        return $this;
    }

    /**
     * @param $categories
     * @return $this
     * @throws VCardException
     */
    public function addCategories($categories): VCard
    {
        $this->setProperty(
            'categories',
            'CATEGORIES' . $this->getCharsetString(),
            trim(implode(',', $categories))
        );

        return $this;
    }

    /**
     * @param $number
     * @param string $type
     * @return $this
     * @throws VCardException
     */
    public function addPhoneNumber($number, string $type = ''): VCard
    {
        $this->setProperty(
            'phoneNumber',
            'TEL' . (($type !== '') ? ';' . $type : ''),
            $number
        );

        return $this;
    }

    /**
     * @param $url
     * @param bool $include
     * @return $this
     * @throws VCardException
     */
    public function addLogo($url, bool $include = true): VCard
    {
        $this->addMedia(
            'LOGO',
            $url,
            $include,
            'logo'
        );

        return $this;
    }

    /**
     * @param $content
     * @return $this
     * @throws VCardException
     */
    public function addLogoContent($content): VCard
    {
        $this->addMediaContent(
            'LOGO',
            $content,
            'logo'
        );

        return $this;
    }

    /**
     * @param $url
     * @param bool $include
     * @return $this
     * @throws VCardException
     */
    public function addPhoto($url, bool $include = true): VCard
    {
        $this->addMedia(
            'PHOTO',
            $url,
            $include,
            'photo'
        );

        return $this;
    }

    /**
     * @param $content
     * @return $this
     * @throws VCardException
     */
    public function addPhotoContent($content): VCard
    {
        $this->addMediaContent(
            'PHOTO',
            $content,
            'photo'
        );

        return $this;
    }

    /**
     * @param $url
     * @param string $type
     * @return $this
     * @throws VCardException
     */
    public function addURL($url, string $type = ''): VCard
    {
        $this->setProperty(
            'url',
            'URL' . (($type !== '') ? ';' . $type : ''),
            $url
        );

        return $this;
    }

    /**
     * Build VCard (.vcf)
     *
     * @return string
     */
    public function buildVCard(): string
    {
        // init string
        $string = "BEGIN:VCARD\r\n";
        $string .= "VERSION:3.0\r\n";
        $string .= "REV:" . date("Y-m-d") . "T" . date("H:i:s") . "Z\r\n";

        // loop all properties
        $properties = $this->getProperties();
        foreach ($properties as $property) {
            // add to string
            $string .= $this->fold($property['key'] . ':' . $this->escape($property['value']) . "\r\n");
        }

        // add to string
        $string .= "END:VCARD\r\n";

        // return
        return $string;
    }

    /**
     * Build VCalender (.ics) - Safari (< iOS 8) can not open .vcf files, so we have build a workaround.
     *
     * @return string
     */
    public function buildVCalendar(): string
    {
        // init dates
        $dtstart = date("Ymd") . "T" . date("Hi") . "00";
        $dtend = date("Ymd") . "T" . date("Hi") . "01";

        // init string
        $string = "BEGIN:VCALENDAR\n";
        $string .= "VERSION:2.0\n";
        $string .= "BEGIN:VEVENT\n";
        $string .= "DTSTART;TZID=Europe/London:" . $dtstart . "\n";
        $string .= "DTEND;TZID=Europe/London:" . $dtend . "\n";
        $string .= "SUMMARY:Click attached contact below to save to your contacts\n";
        $string .= "DTSTAMP:" . $dtstart . "Z\n";
        $string .= "ATTACH;VALUE=BINARY;ENCODING=BASE64;FMTTYPE=text/directory;\n";
        $string .= " X-APPLE-FILENAME=" . $this->getFilename() . "." . $this->getFileExtension() . ":\n";

        // base64 encode it so that it can be used as an attachemnt to the "dummy" calendar appointment
        $b64vcard = base64_encode($this->buildVCard());

        // chunk the single long line of b64 text in accordance with RFC2045
        // (and the exact line length determined from the original .ics file exported from Apple calendar
        $b64mline = chunk_split($b64vcard, 74, "\n");

        // need to indent all the lines by 1 space for the iphone (yes really?!!)
        $b64final = preg_replace('/(.+)/', ' $1', $b64mline);
        $string .= $b64final;

        // output the correctly formatted encoded text
        $string .= "END:VEVENT\n";
        $string .= "END:VCALENDAR\n";

        // return
        return $string;
    }

    /**
     * Returns the browser user agent string.
     *
     * @return string
     */
    protected function getUserAgent(): string
    {
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $browser = strtolower($_SERVER['HTTP_USER_AGENT']);
        } else {
            $browser = 'unknown';
        }

        return $browser;
    }

    /**
     * Decode
     *
     * @param string $value The value to decode
     * @return string decoded
     */
    private function decode(string $value): string
    {
        // convert cyrlic, greek or other caracters to ASCII characters
        return Transliterator::transliterate($value);
    }

    /**
     * Download a vcard or vcal file to the browser.
     */
    public function download(): void
    {
        // define output
        $output = $this->getOutput();

        foreach ($this->getHeaders(false) as $header) {
            header($header);
        }

        // echo the output and it will be a download
        echo $output;
    }

    /**
     * Fold a line according to RFC2425 section 5.8.1.
     * @link http://tools.ietf.org/html/rfc2425#section-5.8.1
     *
     * @param string $text
     * @return false|string
     */
    protected function fold(string $text)
    {
        if (strlen($text) <= 75) {
            return $text;
        }

        // split, wrap and trim trailing separator
        return substr($this->chunk_split_unicode($text, 75, "\r\n "), 0, -3);
    }

    /**
     * multibyte word chunk split
     * @link http://php.net/manual/en/function.chunk-split.php#107711
     *
     * @param string $body The string to be chunked.
     * @param integer $chunklen The chunk length.
     * @param string $end The line ending sequence.
     * @return string            Chunked string
     */
    protected function chunk_split_unicode(string $body, int $chunklen = 76, string $end = "\r\n"): string
    {
        $array = array_chunk(
            preg_split("//u", $body, -1, PREG_SPLIT_NO_EMPTY), $chunklen);
        $body = "";
        foreach ($array as $item) {
            $body .= implode("", $item) . $end;
        }
        return $body;
    }

    /**
     * Escape newline characters according to RFC2425 section 5.8.4.
     *
     * @link http://tools.ietf.org/html/rfc2425#section-5.8.4
     * @param string $text
     * @return string
     */
    protected function escape(string $text): string
    {
        return str_replace(array("\r\n", "\n"), "\\n", $text);
    }

    /**
     * Get output as string
     * @return string
     * @deprecated in the future
     *
     */
    public function get(): string
    {
        return $this->getOutput();
    }

    /**
     * Get charset
     *
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Get charset string
     *
     * @return string
     */
    public function getCharsetString(): string
    {
        return ';CHARSET=' . $this->charset;
    }

    /**
     * Get content type
     *
     * @return string
     */
    public function getContentType(): string
    {
        return ($this->isIOS7()) ?
            'text/x-vcalendar' : 'text/x-vcard';
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename(): string
    {
        if (!$this->filename) {
            return 'unknown';
        }

        return $this->filename;
    }

    /**
     * Get file extension
     *
     * @return string
     */
    public function getFileExtension(): string
    {
        return ($this->isIOS7()) ?
            'ics' : 'vcf';
    }

    /**
     * Get headers
     *
     * @param bool $asAssociative
     * @return array
     */
    public function getHeaders(bool $asAssociative): array
    {
        $contentType = $this->getContentType() . '; charset=' . $this->getCharset();
        $contentDisposition = 'attachment; filename=' . $this->getFilename() . '.' . $this->getFileExtension();
        $contentLength = mb_strlen($this->getOutput(), '8bit');
        $connection = 'close';

        if ($asAssociative) {
            return [
                'Content-type' => $contentType,
                'Content-Disposition' => $contentDisposition,
                'Content-Length' => $contentLength,
                'Connection' => $connection,
            ];
        }

        return [
            'Content-type: ' . $contentType,
            'Content-Disposition: ' . $contentDisposition,
            'Content-Length: ' . $contentLength,
            'Connection: ' . $connection,
        ];
    }

    /**
     * Get output as string
     * iOS devices (and safari < iOS 8 in particular) can not read .vcf (= vcard) files.
     * So I build a workaround to build a .ics (= vcalender) file.
     *
     * @return string
     */
    public function getOutput(): string
    {
        return ($this->isIOS7()) ?
            $this->buildVCalendar() : $this->buildVCard();
    }

    /**
     * Get properties
     *
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Has property
     *
     * @param string $key
     * @return bool
     */
    public function hasProperty($key): bool
    {
        $properties = $this->getProperties();

        foreach ($properties as $property) {
            if ($property['key'] === $key && $property['value'] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Is iOS - Check if the user is using an iOS-device
     *
     * @return bool
     */
    public function isIOS(): bool
    {
        // get user agent
        $browser = $this->getUserAgent();

        return (strpos($browser, 'iphone') || strpos($browser, 'ipod') || strpos($browser, 'ipad'));
    }

    /**
     * Is iOS less than 7 (should cal wrapper be returned)
     *
     * @return bool
     */
    public function isIOS7(): bool
    {
        return ($this->isIOS() && $this->shouldAttachmentBeCal());
    }

    /**
     * Save to a file
     *
     * @return void
     */
    public function save(): void
    {
        $file = $this->getFilename() . '.' . $this->getFileExtension();

        // Add save path if given
        if (null !== $this->savePath) {
            $file = $this->savePath . $file;
        }

        file_put_contents(
            $file,
            $this->getOutput()
        );
    }

    /**
     * Set charset
     *
     * @param mixed $charset
     * @return void
     */
    public function setCharset($charset): void
    {
        $this->charset = $charset;
    }

    /**
     * Set filename
     *
     * @param mixed $value
     * @param bool $overwrite [optional] Default overwrite is true
     * @param string $separator [optional] Default separator is an underscore '_'
     * @return void
     */
    public function setFilename($value, bool $overwrite = true, string $separator = '_'): void
    {
        // recast to string if $value is array
        if (is_array($value)) {
            $value = implode($separator, $value);
        }

        // trim unneeded values
        $value = trim($value, $separator);

        // remove all spaces
        $value = preg_replace('/\s+/', $separator, $value);

        // if value is empty, stop here
        if (empty($value)) {
            return;
        }

        // decode value + lowercase the string
        $value = strtolower($this->decode($value));

        // urlize this part
        $value = Transliterator::urlize($value);

        // overwrite filename or add to filename using a prefix in between
        $this->filename = ($overwrite) ?
            $value : $this->filename . $separator . $value;
    }

    /**
     * Set the save path directory
     *
     * @param string $savePath Save Path
     * @throws VCardException
     */
    public function setSavePath(string $savePath)
    {
        if (!is_dir($savePath)) {
            throw VCardException::outputDirectoryNotExists();
        }

        // Add trailing directory separator the save path
        if (substr($savePath, -1) !== DIRECTORY_SEPARATOR) {
            $savePath .= DIRECTORY_SEPARATOR;
        }

        $this->savePath = $savePath;
    }

    /**
     * Set property
     *
     * @param string $element The element name you want to set, f.e.: name, email, phoneNumber, ...
     * @param string $key
     * @param string $value
     * @throws VCardException
     */
    private function setProperty(string $element, string $key, string $value): void
    {
        if (!in_array($element, $this->multiplePropertiesForElementAllowed, true)
            && isset($this->definedElements[$element])
        ) {
            throw VCardException::elementAlreadyExists($element);
        }

        // we define that we set this element
        $this->definedElements[$element] = true;

        // adding property
        $this->properties[] = [
            'key' => $key,
            'value' => $value
        ];
    }

    /**
     * Checks if we should return vcard in cal wrapper
     *
     * @return bool
     */
    protected function shouldAttachmentBeCal(): bool
    {
        $browser = $this->getUserAgent();

        $matches = [];
        preg_match('/os (\d+)_(\d+)\s+/', $browser, $matches);
        $version = isset($matches[1]) ? ((int)$matches[1]) : 999;

        return ($version < 8);
    }
}

