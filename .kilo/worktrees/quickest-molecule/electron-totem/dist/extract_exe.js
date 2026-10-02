
const fs = require('fs');
const Seven = require('node-7z');
const sevenBin = require('7zip-bin');
const path = require('path');

const exePath = path.resolve('puto.exe');
const extractDir = path.resolve('temp_extract');
if (!fs.existsSync(extractDir)) fs.mkdirSync(extractDir);

console.log('Extracting with 7z binary:', sevenBin.path7za);
const myStream = Seven.extractFull(exePath, extractDir, {
  $bin: sevenBin.path7za
});

myStream.on('end', function () {
  console.log('Extraction complete');
});
myStream.on('error', function (err) {
  console.error('Error:', err);
});

