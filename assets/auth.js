/* WebAuthn credentials stay in the authenticator. No passwords/tokens in browser storage. */
(() => {
 'use strict';
 const config=window.CCS_AUTH;if(!config)return;
 const supported=()=>!!(config.enabled&&window.isSecureContext&&window.PublicKeyCredential&&navigator.credentials);
 const from64=value=>Uint8Array.from(atob(value.replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));
 const to64=value=>btoa(String.fromCharCode(...new Uint8Array(value))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
 async function api(action,body={}){const r=await fetch(config.api+'passkeys/'+action,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json',...(config.nonce?{'X-WP-Nonce':config.nonce}:{})},body:JSON.stringify(body)});let data;try{data=await r.json()}catch{throw new Error('Não foi possível acessar o servidor. Tente novamente.')}if(!r.ok)throw new Error(data.message||'Não foi possível concluir.');return data;}
 const options=raw=>({...raw,challenge:from64(raw.challenge),...(raw.user?{user:{...raw.user,id:from64(raw.user.id)}}:{}),...(raw.excludeCredentials?{excludeCredentials:raw.excludeCredentials.map(c=>({...c,id:from64(c.id)}))}:{}),...(raw.allowCredentials?{allowCredentials:raw.allowCredentials.map(c=>({...c,id:from64(c.id)}))}:{})});
 function friendly(err){if(err.name==='NotAllowedError')return new Error('A verificação foi cancelada ou não foi concluída. Tente novamente ou use sua senha.');if(err.name==='InvalidStateError')return new Error('Este acesso já foi ativado. Use a biometria no login ou escolha outro dispositivo.');if(err.name==='SecurityError')return new Error('Abra o aplicativo pelo endereço HTTPS oficial para ativar a biometria.');if(err.name==='NotSupportedError')return new Error('Este dispositivo não oferece o acesso solicitado. Continue usando sua senha.');return err;}
 window.CCS_PASSKEYS={
  supported,
  async platform(){try{return supported()&&await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()}catch{return false}},
  deviceName(){const ua=navigator.userAgent;return /iPhone|iPad/.test(ua)?'Meu iPhone / iPad':/Android/.test(ua)?'Meu celular Android':/Windows/.test(ua)?'Meu computador Windows':/Macintosh/.test(ua)?'Meu Mac':'Meu dispositivo'},
  async register(label,password){try{const start=await api('register-options',{label,password});const credential=await navigator.credentials.create({publicKey:options(start.options)});return await api('register',{request_id:start.request_id,id:to64(credential.rawId),clientDataJSON:to64(credential.response.clientDataJSON),attestationObject:to64(credential.response.attestationObject)})}catch(err){throw friendly(err)}},
  async revoke(id){return api('revoke',{id})}
 };
 const button=document.querySelector('[data-passkey-login]'),hint=document.getElementById('ccs-biometric-hint');
 if(button){button.disabled=!supported();if(!supported())hint.textContent='Biometria indisponível neste navegador ou endereço. Entre com sua senha.';button.addEventListener('click',async()=>{button.disabled=true;const error=document.getElementById('ccs-auth-error');error.textContent='';try{const start=await api('login-options');const credential=await navigator.credentials.get({publicKey:options(start.options)});const result=await api('login',{request_id:start.request_id,id:to64(credential.rawId),clientDataJSON:to64(credential.response.clientDataJSON),authenticatorData:to64(credential.response.authenticatorData),signature:to64(credential.response.signature),userHandle:credential.response.userHandle?to64(credential.response.userHandle):'',remember:document.querySelector('[name="remember"]')?.checked===true});window.location.assign(result.redirect)}catch(err){error.textContent=friendly(err).message;button.disabled=false}})}
 document.querySelectorAll('[data-toggle-password]').forEach(button=>button.addEventListener('click',()=>{const input=button.parentElement.querySelector('input');const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Ocultar':'Mostrar';button.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha');button.setAttribute('aria-pressed',String(show))}));
 document.querySelectorAll('[data-auth-form]').forEach(form=>form.addEventListener('submit',()=>{const b=form.querySelector('[type="submit"]');b.disabled=true;b.textContent='Aguarde…'}));
})();
