# Face Tag Import Script
This is a barebone utility that can extract photo tags from a Family Tree
Builder GEDCOM file and add them to the database created by
[Faces for webtrees v2.6.2](https://github.com/UksusoFF/webtrees-faces/releases/tag/v2.6.2).

## Requirements
MySQL configured for webtrees 2.0

Beware this script will delete all existing face tags in webtrees.

## Usage
1. Place this script in the webtrees root directory.
1. Place your FTB export in the webtrees root and name it `tree.ged`.
1. Navigate to this script in your browser to run it.
1. When finished, remove this script and remove the `tree.ged` file.