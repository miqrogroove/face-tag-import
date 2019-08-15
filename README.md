# Face Tag Import Script
This is a barebone utility that can extract photo tags from a Family Tree
Builder GEDCOM file and add them to the database created by
[Faces for webtrees v2.2.1](https://github.com/UksusoFF/webtrees-faces/releases/tag/v2.2.1).

## Usage
1. Place this script in the webtrees root directory.
1. Place your FTB export in the webtrees root and name it `tree.ged`.
1. Customize `IMPORT_FILE_PATH` if you'd rather relocate the `tree.ged` file.
1. Beware this script will delete all existing photo tags in webtrees.
1. Log into webtrees as an admin.
1. Navigate to this script in your browser to run it.
1. When finished, remove this script and remove the `tree.ged` file.