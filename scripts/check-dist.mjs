import fs from 'node:fs';
import path from 'node:path';
const required = ['index.html','images/accessible-van.avif','images/accessible-passenger.avif','images/accessible-boarding.avif','images/put-bez-pregrad/favicon.svg','images/put-bez-pregrad/logo.svg','images/put-bez-pregrad/logo-light.svg'];
for(const item of required){
  const p=path.join('dist',item);
  if(!fs.existsSync(p) || fs.statSync(p).size===0)throw Error('Missing or empty: '+p);
}
const html=fs.readFileSync('dist/index.html','utf8');
if(!/assets\/[^" ]+\.js/.test(html) || !/assets\/[^" ]+\.css/.test(html))throw Error('No compiled JS/CSS in HTML');
console.log('Production site files verified');
