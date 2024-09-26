# vidtool.phar

Help me find videos that need to be re-encoded. Some things are still hard coded right now that will be moved to configuration later on.

The default behaviour is to print a report about the video files it found.

# Requirements

* PHP 8.2+

# Checks Performed:

> File Type: mp4

  At the moment my filter is super basic and only checks files that end in dot mp4 file extension.

> Codec: h265 (HEVC)

  Demands videos be in this codec as it is my preferred codec for quality, file size, and hardware support.

> Encoder: HandBrake

  Demands videos had been encoded by HandBrake, as h265 with the same settings as HandBrake from Adobe built encoders end up being absolute ass. This results in encoding files in a much higher bitrate than should have been required to not have Adobe crush the hell out of my videos.

# Usage

> `$ php vidtool.phar check`

Check the current directory for MP4 files.

> `$ php vidtool.phar check <path>`

Check a specific file or directory.

> `$ php vidtool.phar check <path> --move`

Move the files that fail the checks into a `Todo` folder that can then be drag dropped onto HandBrake later.

> `$ php bin\vidtool phar`

From the source directory, this will build a Phar file placed within the `build` directory. This footnote was placed here as passive aggressive bullying of AcmePHP since they stopped shipping Phars, stopped updating the main website, and refuse to post Phar building instructions on the Github repo readme file. All this poor behaviour began when ZeroSSL took the project over.

FWIW if I ever 1.0.0 this, the Phars *will* be available on the Releases page here on Github. Until then you can totally build your own though.
