/* <dither-bg> — two-tone dithered warp texture (WebGL2), brand-colour driven.
   Attributes: back, front, size (px block), scale, speed. Fills its container. */
(function () {
  if (customElements.get('dither-bg')) return;
  var VS = '#version 300 es\nprecision highp float;\nin vec4 a_position;\nvoid main(){gl_Position=a_position;}';
  var FS = [
    '#version 300 es',
    'precision highp float;',
    'uniform vec2 u_resolution;uniform float u_time;uniform float u_pxSize;uniform float u_scale;',
    'uniform vec3 u_back;uniform vec3 u_front;',
    'out vec4 fragColor;',
    'float hash21(vec2 p){p=fract(p*vec2(0.3183099,0.3678794))+0.1;p+=dot(p,p+19.19);return fract(p.x*p.y);}',
    'void main(){',
    '  float t=.5*u_time;',
    '  float pxSize=max(1.0,u_pxSize);',
    '  vec2 c=gl_FragCoord.xy-.5*u_resolution;',
    '  vec2 pxUV=c/pxSize;',
    '  vec2 pix=(floor(pxUV)+.5)*pxSize;',
    '  vec2 uv=pix/u_scale*0.003;',
    '  for(float i=1.0;i<6.0;i++){',
    '    uv.x+=0.6/i*cos(i*2.5*uv.y+t);',
    '    uv.y+=0.6/i*cos(i*1.5*uv.x+t);',
    '  }',
    '  float shape=.15/max(0.001,abs(sin(t-uv.y-uv.x)));',
    '  shape=smoothstep(0.02,1.0,shape);',
    '  float d=step(hash21(pix),shape)-.5;',
    '  float res=step(.5,shape+d);',
    '  fragColor=vec4(mix(u_back,u_front,res),1.0);',
    '}'
  ].join('\n');
  function hexToRgb(hex) {
    var h = (hex || '#000000').replace('#', '').trim();
    return [parseInt(h.slice(0, 2), 16) / 255, parseInt(h.slice(2, 4), 16) / 255, parseInt(h.slice(4, 6), 16) / 255];
  }
  class DitherBG extends HTMLElement {
    connectedCallback() {
      if (this._started) return;
      this._started = true;
      var canvas = document.createElement('canvas');
      canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;display:block';
      if (!this.style.position) this.style.position = 'absolute';
      this.appendChild(canvas);
      var gl = canvas.getContext('webgl2', { antialias: false });
      if (!gl) { this.style.background = this.getAttribute('back') || '#0F2349'; return; }
      var speed = parseFloat(this.getAttribute('speed') || '0.4');
      function sh(type, src) {
        var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s);
        if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) console.error(gl.getShaderInfoLog(s));
        return s;
      }
      var prog = gl.createProgram();
      gl.attachShader(prog, sh(gl.VERTEX_SHADER, VS));
      gl.attachShader(prog, sh(gl.FRAGMENT_SHADER, FS));
      gl.linkProgram(prog); gl.useProgram(prog);
      var buf = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, buf);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), gl.STATIC_DRAW);
      var pos = gl.getAttribLocation(prog, 'a_position');
      gl.enableVertexAttribArray(pos);
      gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);
      var L = {
        res: gl.getUniformLocation(prog, 'u_resolution'),
        time: gl.getUniformLocation(prog, 'u_time'),
        px: gl.getUniformLocation(prog, 'u_pxSize'),
        scale: gl.getUniformLocation(prog, 'u_scale'),
        back: gl.getUniformLocation(prog, 'u_back'),
        front: gl.getUniformLocation(prog, 'u_front')
      };
      var self = this;
      function resize() {
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.max(1, Math.round(self.clientWidth * dpr));
        canvas.height = Math.max(1, Math.round(self.clientHeight * dpr));
        gl.viewport(0, 0, canvas.width, canvas.height);
      }
      this._ro = new ResizeObserver(resize);
      this._ro.observe(this);
      resize();
      var render = function (ms) {
        var back = hexToRgb(self.getAttribute('back') || '#0F2349');
        var front = hexToRgb(self.getAttribute('front') || '#F9F0ED');
        var size = parseFloat(self.getAttribute('size') || '1.6') || 1.6;
        var scale = parseFloat(self.getAttribute('scale') || '1') || 1;
        gl.uniform2f(L.res, canvas.width, canvas.height);
        gl.uniform1f(L.time, ms * 0.001 * speed);
        gl.uniform1f(L.px, size * Math.min(window.devicePixelRatio || 1, 2));
        gl.uniform1f(L.scale, scale);
        gl.uniform3f(L.back, back[0], back[1], back[2]);
        gl.uniform3f(L.front, front[0], front[1], front[2]);
        gl.drawArrays(gl.TRIANGLES, 0, 6);
        self._raf = requestAnimationFrame(render);
      };
      this._raf = requestAnimationFrame(render);
    }
    disconnectedCallback() {
      if (this._ro) this._ro.disconnect();
      if (this._raf) cancelAnimationFrame(this._raf);
      this._started = false;
    }
  }
  customElements.define('dither-bg', DitherBG);
})();
