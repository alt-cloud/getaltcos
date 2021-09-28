#!/bin/sh
set -e
export DOCUMENT_ROOT=$(realpath `dirname $0`'/../../')
. $DOCUMENT_ROOT/ostree/bin/functions.sh

exec 2>&1

if [ $# -gt 4 ]
then
	echo "Help: $0 [<branch>] [<commitid> or <vardir>] [<directory of main ostree repository>]"
	echo "For example: $0  acos/x86_64/sisyphus ac24e repo"
	echo "For example: $0  acos/x86_64/sisyphus out/var repo"
	echo "You can change TMPDIR environment variable to set another directory where temporary files will be stored"
	exit 1
fi

if [ `id -u` -eq 0 ]
then
        echo "ERROR: you can't run $0 as root (uid=0)"
        exit 1
fi

APTDIR="$HOME/apt"

# Set brach variables
BRANCH=${1:-acos/x86_64/sisyphus}
BRANCHREPODIR=`refRepoDir $BRANCH`
BRANCH_REPO=$DOCUMENT_ROOT/ACOS/streams/$BRANCHREPODIR
MAIN_REPO=${3:-$BRANCH_REPO/bare/repo}
if [ ! -d $MAIN_REPO ]
then
	echo "ERROR: ostree repository must exist"
	exit 1
fi
BRANCHDIR=`refToDir $BRANCH`
BRANCH_DIR=$DOCUMENT_ROOT/ACOS/streams/$BRANCHDIR
if [ ! -d  $BRANCH_DIR ]
then
  mkdir -m 0775 -p  $BRANCH_DIR
fi

# Set Commit variables
SHORTCOMMITID=$2
if [ -z $SHORTCOMMITID ]
then
  COMMITID=`lastCommitId $BRANCHDIR`
  VAR_DIR=$BRANCH_REPO/vars/$COMMITID/var
else
  if [[ "$SHORTCOMMITID" == */* ]] # It's VAR_DIR
  then
    VAR_DIR=$SHORTCOMMITID
  else
    COMMITID=`fullCommitId $BRANCHDIR $SHORTCOMMITID`
    VAR_DIR=$BRANCH_DIR/vars/$COMMITID/var
  fi
fi

if [ -z "$COMMITID" ]
then
  echo "ERROR: Commit $SHORTCOMMITID must exist"
  exit 1
fi

IMAGE_DIR="$BRANCH_DIR/images"
if [ ! -d $IMAGE_DIR ]
then
  mkdir -m 0775 -p $IMAGE_DIR
fi
OUT_DIR="$IMAGE_DIR/iso"
if [ ! -d $OUT_DIR ]
then
  mkdir -m 0775 -p $OUT_DIR
fi

RPMBUILD_DIR=`mktemp --tmpdir -d acos_make_iso_rpmbuild-XXXXXX`
mkdir $RPMBUILD_DIR/SOURCES
sudo tar -cf - -C $VAR_DIR . | xz -9 -c - > $RPMBUILD_DIR/SOURCES/var.tar.xz

mkdir $RPMBUILD_DIR/acos_root
ostree admin init-fs --modern $RPMBUILD_DIR/acos_root
sudo ostree pull-local --repo $RPMBUILD_DIR/acos_root/ostree/repo $MAIN_REPO $BRANCH
sudo tar -cf - -C $RPMBUILD_DIR/acos_root . | xz -9 -c -T0 - > $RPMBUILD_DIR/SOURCES/acos_root.tar.xz
sudo rm -rf $RPMBUILD_DIR/acos_root

rpmbuild --define "_topdir $RPMBUILD_DIR" --define "_rpmdir $APTDIR/x86_64/RPMS.dir/" --define "_rpmfilename acos-archives-0.1-alt1.x86_64.rpm" -bb acos-archives.spec

sudo rm -rf $RPMBUILD_DIR

sudo chmod a+w $OUT_DIR
make -C "$DOCUMENT_ROOT/../mkimage-profiles" APTCONF=$APTDIR/apt.conf.sisyphus.x86_64  BRANCH=sisyphus IMAGEDIR=$OUT_DIR installer-acos.iso
mv `realpath $OUT_DIR/installer-acos-latest-x86_64.iso` $OUT_DIR/`refVersion $BRANCH $COMMITID`.iso
find $OUT_DIR -type l -delete
