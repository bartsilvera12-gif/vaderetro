/* <velaris-bg> — animated simplex-noise gradient background (WebGL), adapted for VADE RETRO.
   Attributes: bg, colors (comma-separated, up to 4), speed, grain. Fills its container. */
(function () {
  if (customElements.get('velaris-bg')) return;
  var VS = 'attribute vec2 position;varying vec2 vUv;void main(){vUv=position*0.5+0.5;gl_Position=vec4(position,0.0,1.0);}';
  var FS = [
    'precision highp float;varying vec2 vUv;',
    'uniform vec2 u_resolution;uniform float u_time;uniform float u_grain;uniform vec3 u_colors[4];uniform vec3 u_bg;',
    'vec3 permute(vec3 x){return mod(((x*34.0)+1.0)*x,289.0);}',
    'float snoise(vec2 v){const vec4 C=vec4(0.211324865405187,0.366025403784439,-0.577350269189626,0.024390243902439);',
    'vec2 i=floor(v+dot(v,C.yy));vec2 x0=v-i+dot(i,C.xx);',
    'vec2 i1=(x0.x>x0.y)?vec2(1.0,0.0):vec2(0.0,1.0);vec4 x12=x0.xyxy+C.xxzz;x12.xy-=i1;i=mod(i,289.0);',
    'vec3 p=permute(permute(i.y+vec3(0.0,i1.y,1.0))+i.x+vec3(0.0,i1.x,1.0));',
    'vec3 m=max(0.5-vec3(dot(x0,x0),dot(x12.xy,x12.xy),dot(x12.zw,x12.zw)),0.0);m=m*m;m=m*m;',
    'vec3 x=2.0*fract(p*C.www)-1.0;vec3 h=abs(x)-0.5;vec3 ox=floor(x+0.5);vec3 a0=x-ox;',
    'm*=1.79284291400159-0.85373472095314*(a0*a0+h*h);',
    'vec3 g;g.x=a0.x*x0.x+h.x*x0.y;g.yz=a0.yz*x12.xz+h.yz*x12.yw;return 130.0*dot(m,g);}',
    'void main(){vec2 uv=vUv;float ratio=u_resolution.x/u_resolution.y;vec2 p=uv-0.5;p.x*=ratio;',
    'float t=u_time*0.1;',
    'float n1=snoise(p*0.4+vec2(t*0.2,-t*0.3));',
    'float n2=snoise(p*0.55+vec2(-t*0.15,t*0.25)+n1*0.25);',
    'float n3=snoise(p*0.75+vec2(t*0.1,-t*0.2)+n2*0.2);',
    'vec3 col=u_bg;float dist=length(p)*1.5;float vignette=1.0-smoothstep(0.3,1.2,dist);',
    'col=mix(col,u_colors[0],smoothstep(-0.2,0.5,n1)*0.85);',
    'col=mix(col,u_colors[1],smoothstep(-0.1,0.6,n2)*0.7);',
    'col=mix(col,u_colors[2],smoothstep(-0.3,0.4,n3)*0.6);',
    'col=mix(col,u_colors[3],smoothstep(0.0,0.7,n1*n2)*0.5);',
    'float glow=smoothstep(0.8,0.0,dist)*0.3;col+=u_colors[1]*glow;',
    'col=mix(col*0.2,col,vignette);',
    'float grain=fract(sin(dot(uv,vec2(12.9898,78.233)))*43758.5453+u_time);',
    'col+=(grain-0.5)*u_grain*0.1;gl_FragColor=vec4(col,1.0);}'
  ].join('\n');
  function hexToRgb(hex) {
    var h = hex.replace('#', '').trim();
    return [parseInt(h.slice(0, 2), 16) / 255, parseInt(h.slice(2, 4), 16) / 255, parseInt(h.slice(4, 6), 16) / 255];
  }
  class VelarisBG extends HTMLElement {
    connectedCallback() {
      if (this._started) return;
      this._started = true;
      this.style.display = this.style.display || 'block';
      var canvas = document.createElement('canvas');
      canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none';
      if (!this.style.position) this.style.position = 'absolute';
      this.appendChild(canvas);
      var gl = canvas.getContext('webgl');
      if (!gl) return;
      var bg = this.getAttribute('bg') || '#0F2349';
      var colors = (this.getAttribute('colors') || '#0F2349,#16305e,#571E0B,#CF9D59').split(',');
      while (colors.length < 4) colors.push(colors[colors.length - 1]);
      var speed = parseFloat(this.getAttribute('speed') || '1');
      var grain = parseFloat(this.getAttribute('grain') || '0.15');
      function shader(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
      var prog = gl.createProgram();
      gl.attachShader(prog, shader(gl.VERTEX_SHADER, VS));
      gl.attachShader(prog, shader(gl.FRAGMENT_SHADER, FS));
      gl.linkProgram(prog); gl.useProgram(prog);
      var buf = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, buf);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
      var pos = gl.getAttribLocation(prog, 'position');
      gl.enableVertexAttribArray(pos);
      gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);
      var locs = {
        res: gl.getUniformLocation(prog, 'u_resolution'),
        time: gl.getUniformLocation(prog, 'u_time'),
        grain: gl.getUniformLocation(prog, 'u_grain'),
        colors: gl.getUniformLocation(prog, 'u_colors'),
        bg: gl.getUniformLocation(prog, 'u_bg')
      };
      var self = this;
      function resize() {
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.max(1, self.clientWidth * dpr);
        canvas.height = Math.max(1, self.clientHeight * dpr);
        gl.viewport(0, 0, canvas.width, canvas.height);
      }
      this._ro = new ResizeObserver(resize);
      this._ro.observe(this);
      resize();
      var flat = new Float32Array(colors.slice(0, 4).map(hexToRgb).flat());
      var bgc = hexToRgb(bg);
      var render = function (t) {
        gl.uniform2f(locs.res, canvas.width, canvas.height);
        gl.uniform1f(locs.time, t * 0.001 * speed);
        gl.uniform1f(locs.grain, grain);
        gl.uniform3f(locs.bg, bgc[0], bgc[1], bgc[2]);
        gl.uniform3fv(locs.colors, flat);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
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
  customElements.define('velaris-bg', VelarisBG);
})();
