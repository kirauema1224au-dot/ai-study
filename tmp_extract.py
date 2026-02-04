from pathlib import Path
text=Path('api/study/requirements.html').read_text(encoding='utf-8')
start=text.find('<script>')
end=text.rfind('</script>')
script=text[start+8:end]
print('len',len(script))
Path('tmp_script.js').write_text(script,encoding='utf-8')
print('written tmp_script.js')
