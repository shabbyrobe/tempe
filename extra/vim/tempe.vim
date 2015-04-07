if exists("b:current_syntax")
  finish
endif

syn match  tempeEsc       '{{;' 
syn match  tempeHandler   '[a-zA-Z_\.\-0-9][a-zA-Z_/\.\-0-9]*'  nextgroup=tempeArg skipwhite contained
syn match  tempeArg       '[^}|[:space:]]\+'  nextgroup=tempeArg,tempePipe skipwhite contained
syn match  tempePipe      '|'   nextgroup=tempeHandler contained
syn region tempeTag       matchgroup=tempeTag start=+{{[^;]+ end=+}}+ contains=tempeHandler

hi def link tempeEsc      Comment
hi def link tempeHandler  Type
hi def link tempeArg      Identifier
hi def link tempePipe     Operator
hi def link tempeTag      Label

