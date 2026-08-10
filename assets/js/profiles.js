(function(){
  'use strict';
  const ui=window.SPDProfileUI||{};
  const qs=(s,r=document)=>Array.prototype.slice.call(r.querySelectorAll(s));

  async function copyUrl(url,button){
    try{
      if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(url);}else{
        const t=document.createElement('textarea');t.value=url;t.setAttribute('readonly','');t.style.position='fixed';t.style.opacity='0';document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();
      }
      if(button){const old=button.textContent;button.textContent=ui.copiedText||'Link copied';setTimeout(()=>{button.textContent=old;},1600);}
    }catch(e){window.prompt('Copy profile link',url);}
  }

  qs('[data-spd-copy]').forEach(btn=>btn.addEventListener('click',()=>copyUrl(btn.dataset.url||location.href,btn)));
  qs('[data-spd-share]').forEach(btn=>btn.addEventListener('click',async()=>{
    const url=btn.dataset.url||location.href;
    if(navigator.share){try{await navigator.share({title:document.title,text:ui.shareText||'',url:url});return;}catch(e){if(e&&e.name==='AbortError')return;}}
    copyUrl(url,btn);
  }));

  // Minimal first-party QR encoder: QR version 5, error-correction level L, byte mode.
  // It deliberately supports only <=106 UTF-8 bytes, enough for the signed File 03 short URL.
  function gfMul(x,y){let z=0;for(let i=7;i>=0;i--){z=(z<<1)^((z>>>7)*0x11d);if(((y>>>i)&1)!==0)z^=x;}return z&255;}
  function rsDivisor(degree){const res=new Uint8Array(degree);res[degree-1]=1;let root=1;for(let i=0;i<degree;i++){for(let j=0;j<degree;j++){res[j]=gfMul(res[j],root);if(j+1<degree)res[j]^=res[j+1];}root=gfMul(root,2);}return res;}
  function rsRemainder(data,div){const res=new Uint8Array(div.length);for(const b of data){const f=b^res[0];res.copyWithin(0,1);res[res.length-1]=0;for(let i=0;i<res.length;i++)res[i]^=gfMul(div[i],f);}return res;}
  function appendBits(arr,val,len){for(let i=len-1;i>=0;i--)arr.push((val>>>i)&1);}
  function toCodewords(text){const bytes=new TextEncoder().encode(text);if(bytes.length>106)throw new Error('QR payload too long');const bits=[];appendBits(bits,4,4);appendBits(bits,bytes.length,8);bytes.forEach(b=>appendBits(bits,b,8));const cap=108*8;appendBits(bits,0,Math.min(4,cap-bits.length));while(bits.length%8)bits.push(0);const data=[];for(let i=0;i<bits.length;i+=8){let b=0;for(let j=0;j<8;j++)b=(b<<1)|bits[i+j];data.push(b);}for(let pad=0;data.length<108;pad++)data.push((pad&1)?0x11:0xEC);const ecc=Array.from(rsRemainder(Uint8Array.from(data),rsDivisor(26)));return data.concat(ecc);}
  function formatBits(mask){let data=(1<<3)|mask, rem=data;for(let i=0;i<10;i++)rem=(rem<<1)^(((rem>>>9)&1)*0x537);return ((data<<10)|rem)^0x5412;}
  function qrMatrix(text){
    const size=37,modules=Array.from({length:size},()=>Array(size).fill(false)),func=Array.from({length:size},()=>Array(size).fill(false));
    const setf=(x,y,d)=>{if(x>=0&&y>=0&&x<size&&y<size){modules[y][x]=!!d;func[y][x]=true;}};
    function finder(cx,cy){for(let dy=-4;dy<=4;dy++)for(let dx=-4;dx<=4;dx++){const x=cx+dx,y=cy+dy,dist=Math.max(Math.abs(dx),Math.abs(dy));setf(x,y,dist!==2&&dist!==4);}}
    finder(3,3);finder(size-4,3);finder(3,size-4);
    for(let i=8;i<size-8;i++){setf(6,i,(i&1)===0);setf(i,6,(i&1)===0);}
    // Version 5 alignment centers: [6,30]; only (30,30) does not overlap a finder.
    for(let dy=-2;dy<=2;dy++)for(let dx=-2;dx<=2;dx++)setf(30+dx,30+dy,Math.max(Math.abs(dx),Math.abs(dy))!==1);
    // Reserve and later write format areas.
    for(let i=0;i<=5;i++){setf(8,i,false);setf(i,8,false);}setf(8,7,false);setf(8,8,false);setf(7,8,false);
    for(let i=9;i<15;i++)setf(14-i,8,false);
    for(let i=0;i<8;i++)setf(size-1-i,8,false);
    for(let i=8;i<15;i++)setf(8,size-15+i,false);
    setf(8,size-8,true);
    const cw=toCodewords(text),dataBits=[];cw.forEach(b=>appendBits(dataBits,b,8));let bi=0,up=true;
    for(let right=size-1;right>=1;right-=2){if(right===6)right--;for(let v=0;v<size;v++){const y=up?size-1-v:v;for(let j=0;j<2;j++){const x=right-j;if(func[y][x])continue;let bit=bi<dataBits.length?dataBits[bi++]:0;if(((x+y)&1)===0)bit^=1;modules[y][x]=!!bit;}}up=!up;}
    const fmt=formatBits(0),gb=i=>((fmt>>>i)&1)!==0;
    for(let i=0;i<=5;i++)setf(8,i,gb(i));setf(8,7,gb(6));setf(8,8,gb(7));setf(7,8,gb(8));for(let i=9;i<15;i++)setf(14-i,8,gb(i));
    for(let i=0;i<8;i++)setf(size-1-i,8,gb(i));for(let i=8;i<15;i++)setf(8,size-15+i,gb(i));setf(8,size-8,true);
    return modules;
  }
  function renderQr(el,url){
    try{
      const m=qrMatrix(url),n=m.length,q=4,parts=[`<svg viewBox="0 0 ${n+2*q} ${n+2*q}" role="img" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="white"/>`];
      for(let y=0;y<n;y++)for(let x=0;x<n;x++)if(m[y][x])parts.push(`<rect x="${x+q}" y="${y+q}" width="1" height="1" fill="black"/>`);parts.push('</svg>');el.innerHTML=parts.join('');
    }catch(e){el.textContent=url;el.classList.add('spd-qr--fallback');}
  }
  qs('[data-spd-qr]').forEach(el=>renderQr(el,el.dataset.url||location.href));

  qs('.spd-form').forEach(form=>form.addEventListener('submit',()=>{
    const button=form.querySelector('button[type="submit"]');
    if(button){button.disabled=true;button.setAttribute('aria-busy','true');}
  }));

  if(new URLSearchParams(location.search).get('print_profile')==='1'){window.addEventListener('load',()=>window.print(),{once:true});}
})();
