# Maintainer: Mario Aichinger <aichingm@gmail.com>
_pkgname=dcc
_script=$_pkgname
pkgname="$_pkgname""-git"
pkgver=20160806103040
pkgrel=1
pkgdesc="dict.cc cli client"
arch=("any")
Url="https://github.com/aichingm/$_pkgname"
license=('MIT')
depends=("php>=7")
makedepends=("git")
provides=("$_pkgname")
source=("git+$Url.git")
md5sums=('SKIP')

pkgver() {
    cd $_pkgname
    date --date="$(git log -1 --date=iso --format=%cd)" "+%Y%m%d%H%M%S"
}

package() {
	cd "$srcdir/$_pkgname"
    install -Dm755 $_script "$pkgdir/usr/bin/dcc"
}
