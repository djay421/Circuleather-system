#!/usr/bin/perl
use strict;
use warnings;

# Build self-contained previews: inline style.css + base64-embedded fonts.
my ($cssPath, @htmls) = @ARGV;
die "usage: build_preview.pl style.css page1.html page2.html\n" unless $cssPath && @htmls;

open my $cssfh, '<', $cssPath or die "cannot read $cssPath: $!";
local $/;
my $css = <$cssfh>;
close $cssfh;

# Embed the three Poppins weights as data URIs.
for my $w (400, 600, 700) {
    my $fontFile = $cssPath =~ s{[^/\\]+$}{fonts/poppins-$w.woff2}r;
    open my $ffh, '<:raw', $fontFile or die "cannot read $fontFile: $!";
    local $/;
    my $b64;
    {
        my $chunk;
        while (read($ffh, $chunk, 57 * 128)) { $b64 .= substr($chunk, 0, length $chunk); }
    }
    close $ffh;
    $b64 = do {
        require MIME::Base64;
        MIME::Base64::encode_base64($b64, '');
    };
    $css =~ s{url\("fonts/poppins-$w\.woff2"\)\s*format\("woff2"\)}
             {url(data:font/woff2;base64,$b64) format("woff2")}g;
}

for my $html (@htmls) {
    open my $fh, '<', $html or die "cannot read $html: $!";
    local $/;
    my $doc = <$fh>;
    close $fh;

    $doc =~ s{<link rel="stylesheet" href="style.css">}
             {<style>\n$css\n</style>} or do {
        warn "no stylesheet link found in $html\n";
        next;
    };

    open my $out, '>', $html or die "cannot write $html: $!";
    print {$out} $doc;
    close $out;
    print "inlined: $html\n";
}
