const fs = require('fs');
const path = require('path');

function checkFile(filename) {
    const filePath = path.join(__dirname, filename);
    if (!fs.existsSync(filePath)) {
        console.log(`${filename} does not exist`);
        return;
    }
    const fd = fs.openSync(filePath, 'r');
    const buffer = Buffer.alloc(20);
    fs.readSync(fd, buffer, 0, 20, 0);
    fs.closeSync(fd);
    
    console.log(`${filename} first 20 bytes:`);
    console.log('Hex:', buffer.toString('hex'));
    console.log('String:', buffer.toString('utf8').replace(/[^ -~]/g, '.'));
}

checkFile('sok_wednesday_details.html');
checkFile('sok_weekend_details.html');
checkFile('sok_weekly.html');
checkFile('sok.html');
checkFile('sok_firsat.html');
