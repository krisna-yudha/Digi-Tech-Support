import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import fs from 'fs';
import https from 'https';
import http from 'http';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  // Dynamically resolve API URL from VITE_API_URL or process.env or fallback to production backend
  const apiUrl = env.VITE_API_URL || process.env.VITE_API_URL || 'https://apits.gentz.me/api';
  const backendBaseUrl = apiUrl.replace(/\/api\/?$/, '');

  return {
    plugins: [
      vue(),
      {
        name: 'smart-storage-proxy',
        configureServer(server) {
          server.middlewares.use((req, res, next) => {
            if (req.url.startsWith('/storage/')) {
              const cleanUrl = req.url.split('?')[0];
              const relativePath = decodeURIComponent(cleanUrl.replace(/^\/storage\//, ''));
              const localFilePath = path.resolve(__dirname, '../backend/public/storage', relativePath);

              const serveFile = (filePath) => {
                const ext = path.extname(filePath).toLowerCase();
                const mimeMap = {
                  '.png': 'image/png',
                  '.jpg': 'image/jpeg',
                  '.jpeg': 'image/jpeg',
                  '.gif': 'image/gif',
                  '.svg': 'image/svg+xml',
                  '.webp': 'image/webp'
                };
                res.setHeader('Content-Type', mimeMap[ext] || 'application/octet-stream');
                res.setHeader('Access-Control-Allow-Origin', '*');
                res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
                return fs.createReadStream(filePath).pipe(res);
              };

              // 1. Direct match on local disk in ../backend/public/storage/
              if (fs.existsSync(localFilePath) && fs.statSync(localFilePath).isFile()) {
                return serveFile(localFilePath);
              }

              // 2. Settings logo fallback: if exact hash not found locally, serve the uploaded logo from settings folder
              if (relativePath.startsWith('settings/')) {
                const settingsDir = path.resolve(__dirname, '../backend/public/storage/settings');
                if (fs.existsSync(settingsDir)) {
                  const files = fs.readdirSync(settingsDir).filter(f => !f.startsWith('.') && fs.statSync(path.join(settingsDir, f)).isFile());
                  if (files.length > 0) {
                    return serveFile(path.join(settingsDir, files[files.length - 1]));
                  }
                }
              }

              // 3. Signatures fallback: if exact hash not found locally, serve uploaded signature from signatures folder
              if (relativePath.startsWith('signatures/')) {
                const sigDir = path.resolve(__dirname, '../backend/public/storage/signatures');
                if (fs.existsSync(sigDir)) {
                  const files = fs.readdirSync(sigDir).filter(f => !f.startsWith('.') && fs.statSync(path.join(sigDir, f)).isFile());
                  if (files.length > 0) {
                    return serveFile(path.join(sigDir, files[0]));
                  }
                }
              }

              // 4. Try remote proxy to dynamic backendBaseUrl
              const remoteUrl = `${backendBaseUrl}/storage/${relativePath}`;
              const client = remoteUrl.startsWith('https') ? https : http;

              const proxyReq = client.get(remoteUrl, (proxyRes) => {
                if (proxyRes.statusCode >= 200 && proxyRes.statusCode < 300) {
                  res.writeHead(proxyRes.statusCode, {
                    'Content-Type': proxyRes.headers['content-type'] || 'image/png',
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Methods': 'GET, OPTIONS'
                  });
                  proxyRes.pipe(res);
                } else if (proxyRes.statusCode === 301 || proxyRes.statusCode === 302 || proxyRes.statusCode === 307) {
                  const redirectUrl = proxyRes.headers['location'];
                  if (redirectUrl) {
                    const redirectClient = redirectUrl.startsWith('https') ? https : http;
                    redirectClient.get(redirectUrl, (redRes) => {
                      res.writeHead(redRes.statusCode, {
                        'Content-Type': redRes.headers['content-type'] || 'image/png',
                        'Access-Control-Allow-Origin': '*',
                        'Access-Control-Allow-Methods': 'GET, OPTIONS'
                      });
                      redRes.pipe(res);
                    }).on('error', () => {
                      res.statusCode = 404;
                      res.end('Image not found');
                    });
                    return;
                  }
                  res.statusCode = 404;
                  res.end('Image not found');
                } else {
                  res.statusCode = 404;
                  res.end('Image not found');
                }
              });

              proxyReq.on('error', () => {
                res.statusCode = 404;
                res.end('Image proxy error');
              });
              return;
            }
            next();
          });
        }
      }
    ],
    server: {
      port: 5173
    }
  };
});
