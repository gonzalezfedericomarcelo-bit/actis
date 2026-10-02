const fs = require('fs');
const readline = require('readline');

async function processLineByLine() {
  const fileStream = fs.createReadStream('C:\\Users\\HACKRO\\.gemini\\antigravity-ide\\brain\\604d106d-0ad7-4a7b-9c9e-0f95a356fd51\\.system_generated\\logs\\transcript_full.jsonl');

  const rl = readline.createInterface({
    input: fileStream,
    crlfDelay: Infinity
  });

  for await (const line of rl) {
    if (line.includes('tamp_login_iosfa.user.js')) {
      let obj = JSON.parse(line);
      if (obj.content && obj.content.includes('// ==UserScript==') && obj.content.includes('tamp_login_iosfa.user.js')) {
        fs.appendFileSync('found_script.txt', obj.content + "\n\n-----------------\n\n");
      }
    }
  }
}

processLineByLine();
