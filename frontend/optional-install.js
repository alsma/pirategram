import fs from 'fs'
import crypto from 'crypto'
import childProcess from 'child_process'

function fileHash(filename, algorithm = 'md5') {
  return new Promise((resolve, reject) => {
    // Algorithm depends on availability of OpenSSL on platform
    // Another algorithms: 'sha1', 'md5', 'sha256', 'sha512' ...
    const shasum = crypto.createHash(algorithm)
    try {
      const s = fs.ReadStream(filename)
      s.on('data', function (data) {
        shasum.update(data)
      })
      // making digest
      s.on('end', function () {
        const hash = shasum.digest('hex')
        return resolve(hash)
      })
    } catch (error) {
      return reject(new Error('calc fail'))
    }
  })
}

console.log('')
console.log('')
console.log('=====')
console.log('')
console.log('')

const HASH_FILE = './node_modules/package-lock-hash'

const main = async () => {
  const currentHash = await fileHash('./package-lock.json')
  console.log(`package-lock.json hash is ${currentHash}`)
  try {
    console.log('Reading installed node_modules hash from `./node_modules/package-lock-hash`')
    const oldHash = fs.readFileSync(HASH_FILE, { encoding: 'utf8' })
    console.log(`Installed package-lock.json hash is ${oldHash}`)
    if (currentHash === oldHash) {
      console.log('node_modules are NOT changed. Skip install')
      process.exit(0)
    } else {
      fs.unlinkSync(HASH_FILE)
      console.log('node_modules are changed. Run npm install')
    }
  } catch (e) {
    console.log('Run npm install (no hash found)')
  }
  childProcess.execSync('npm install')
  try {
    fs.writeFileSync(HASH_FILE, currentHash, { flag: 'w' })
    console.log('package-lock.json hash is saved to ./node_modules/package-lock-hash')
  } catch (e) {
    console.log('Could not save hash')
    process.exit(1)
  }
}

main()
