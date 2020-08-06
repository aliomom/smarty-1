import 'dart:ffi';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class ImageAsIcon extends StatelessWidget {
  final String img;
  final double height;
  final double width;
  ImageAsIcon({@required this.img,@required this.height,@required this.width})
      :assert(img != null && height != null && width != null);
  @override
  Widget build(BuildContext context) {
    return Tab(
      icon: Container(
        child: Image(

          image: AssetImage(img),
          fit: BoxFit.cover,
        ),
        height: height,
        width: width,
      ),
    );
  }
}
