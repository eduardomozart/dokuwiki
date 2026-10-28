<?php

/**
 * Class renderer_xhtml_test
 */
class renderer_metadata_test extends DokuWikiTest {
    /** @var Doku_Renderer_xhtml */
    protected $R;

    /**
     * Called for each test
     *
     * @throws Exception
     */
    function setUp() : void {
        parent::setUp();
        $this->R = new Doku_Renderer_metadata();
    }

    function tearDown() : void {
        unset($this->R);
    }


    function test_footnote_and_abstract() {
        // avoid issues with the filectime() & filemtime in document_start() & document_end()
        $now = time();
        $this->R->persistent['date']['created'] = $now;
        $this->R->persistent['date']['modified'] = $now;

        $this->R->document_start();

        $this->R->cdata("abstract: ");

        $this->R->footnote_open();
        $this->R->cdata(str_pad("footnote: ", Doku_Renderer_metadata::ABSTRACT_MAX, "lotsa junk "));
        $this->R->footnote_close();

        $this->R->cdata("abstract end.");

        $this->R->document_end();

        $expected = 'abstract: abstract end.';
        $this->assertEquals($expected, $this->R->meta['description']['abstract']);
    }

    public function test_interwikilink_this_media_endpoints() {
        global $ID;
        $ID = 'wiki:test';

        $file = mediaFN('wiki:dokuwiki.png');
        io_makeFileDir($file);
        touch($file);

        $uris = [
            'lib/exe/fetch.php?media=wiki:dokuwiki.png',
            'lib/exe/detail.php?media=wiki:dokuwiki.png',
            'lib/exe/fetch.php?tok=123&media=wiki:dokuwiki.png&w=400',
            'lib/exe/fetch.php?media=wiki%3Adokuwiki.png',
            '_media/wiki:dokuwiki.png',
            '_detail/wiki:dokuwiki.png',
            '_media/wiki/dokuwiki.png',
            'lib/exe/fetch.php/wiki:dokuwiki.png',
            'lib/exe/fetch.php/wiki/dokuwiki.png',
        ];

        foreach ($uris as $uri) {
            $this->R->meta = [];
            $this->R->interwikilink('this>' . $uri, 'Download', 'this', $uri);
            $this->assertArrayHasKey(
                'wiki:dokuwiki.png',
                $this->R->meta['relation']['media'] ?? [],
                "Failed asserting media relation recorded for uri: $uri"
            );
            $this->assertTrue($this->R->meta['relation']['media']['wiki:dokuwiki.png']);
        }

        // Test non-existent media
        $this->R->meta = [];
        $this->R->interwikilink(
            'this>lib/exe/fetch.php?media=nonexistent:file.pdf',
            'PDF',
            'this',
            'lib/exe/fetch.php?media=nonexistent:file.pdf'
        );
        $this->assertArrayHasKey('nonexistent:file.pdf', $this->R->meta['relation']['media'] ?? []);
        $this->assertFalse($this->R->meta['relation']['media']['nonexistent:file.pdf']);
    }

    public function test_interwikilink_this_page_endpoints() {
        global $ID;
        $ID = 'wiki:test';

        $uris = [
            'doku.php?id=wiki:syntax',
            'lib/exe/doku.php?id=wiki:syntax',
            'doku.php?id=wiki:syntax&do=edit#section',
            'doku.php?id=wiki%3Asyntax',
            'doku.php/wiki:syntax',
            'doku.php/wiki/syntax',
        ];

        foreach ($uris as $uri) {
            $this->R->meta = [];
            $this->R->interwikilink('this>' . $uri, 'Syntax', 'this', $uri);
            $this->assertArrayHasKey(
                'wiki:syntax',
                $this->R->meta['relation']['references'] ?? [],
                "Failed asserting reference relation recorded for uri: $uri"
            );
            $this->assertTrue($this->R->meta['relation']['references']['wiki:syntax']);
        }

        // Relative page ID resolves relative to $ID context (wiki:test)
        $this->R->meta = [];
        $this->R->interwikilink('this>doku.php?id=syntax', 'Syntax', 'this', 'doku.php?id=syntax');
        $this->assertArrayHasKey('wiki:syntax', $this->R->meta['relation']['references'] ?? []);

        // Explicit root page ID (:syntax) resolves to root namespace
        $this->R->meta = [];
        $this->R->interwikilink('this>doku.php?id=:syntax', 'Syntax', 'this', 'doku.php?id=:syntax');
        $this->assertArrayHasKey('syntax', $this->R->meta['relation']['references'] ?? []);
    }

    public function test_interwikilink_external_wikis_ignored() {
        $this->R->meta = [];
        $this->R->interwikilink(
            'wp>fetch.php?media=wiki:dokuwiki.png',
            'Link',
            'wp',
            'fetch.php?media=wiki:dokuwiki.png'
        );
        $this->assertEmpty($this->R->meta['relation']['media'] ?? []);

        $this->R->interwikilink(
            'doku>doku.php?id=wiki:syntax',
            'Link',
            'doku',
            'doku.php?id=wiki:syntax'
        );
        $this->assertEmpty($this->R->meta['relation']['references'] ?? []);
    }

    public function test_interwikilink_image_label_and_destination() {
        global $ID;
        $ID = 'wiki:test';

        $this->R->meta = [];
        $imageLabel = [
            'type' => 'internalmedia',
            'src' => 'wiki:dokuwiki.png',
            'title' => 'Icon'
        ];

        $this->R->interwikilink(
            'this>lib/exe/fetch.php?media=docs:manual.pdf',
            $imageLabel,
            'this',
            'lib/exe/fetch.php?media=docs:manual.pdf'
        );

        $media = $this->R->meta['relation']['media'] ?? [];
        $this->assertArrayHasKey('wiki:dokuwiki.png', $media);
        $this->assertArrayHasKey('docs:manual.pdf', $media);
    }

    public function test_render_metadata_interwiki() {
        $page = 'wiki:metadata_interwiki_test';
        $text = '[[this>lib/exe/fetch.php?media=wiki:dokuwiki.png|Download]] [[this>doku.php?id=wiki:syntax|Syntax Page]]';
        saveWikiText($page, $text, 'Test interwiki metadata');

        $meta = p_get_metadata($page);
        $this->assertArrayHasKey('wiki:dokuwiki.png', $meta['relation']['media'] ?? []);
        $this->assertArrayHasKey('wiki:syntax', $meta['relation']['references'] ?? []);
    }
}
