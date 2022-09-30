#!/bin/bash
git fetch --all
git reset --hard origin/master
git clean -df # for untracked files (except ignored, git clean -dfx will remove ignored file too)

