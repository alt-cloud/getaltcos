#!/bin/sh
set -x
exec 2>&1
refDir=$1
commitId=$2
version=$3
ref=$4
nextVersionVarSubDir=$5
repoBarePath="$DOCUMENT_ROOT/ACOS/streams/$refDir/bare/repo";
rootsPath="$DOCUMENT_ROOT/ACOS/streams/$refDir/roots";
varsPath="$DOCUMENT_ROOT/ACOS/streams/$refDir/vars";

cd $rootsPath
newCommitId=`sudo ostree commit \
        --repo=$repoBarePath \
        --tree=dir=$commitId \
        -b $ref  \
        --no-bindings \
        --mode-ro-executables \
        --add-metadata-string=version=$version
`
sudo ostree  summary --repo=$repoBarePath --update
sudo mv $commitId $newCommitId
sudo ln -sf $newCommitId root
cd $varsPath
ln -sf $nextVersionVarSubDir $newCommitId
