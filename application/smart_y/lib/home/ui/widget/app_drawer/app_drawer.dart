import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:inject/inject.dart';
import 'package:smarty/persistence/shared_preferences/shared_preferences_helper.dart';

import '../../../home_module.dart';

@provide
@singleton
class AppDrawerWidget extends StatelessWidget {
  final SharedPreferencesHelper _preferencesHelper= new SharedPreferencesHelper();
  AppDrawerWidget( );
  @override
  Widget build(BuildContext context) {
    return Container(
      width: MediaQuery.of(context).size.width*0.75,
      child: Drawer(

        child: Container(

          decoration: BoxDecoration(
              gradient: LinearGradient(
                  colors: [Color(0xff30124E), Color(0xff1F2E35)],
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                  stops: [0.6, 1])),

          child: ListView(

            children: <Widget>[
              Container(
                padding: EdgeInsetsDirectional.fromSTEB(10, 20, 10, 10),
                child: Row(

                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Image(
                      height: 75,
                      width: 75,
                      image: AssetImage('assets/Rectangle16.png'),
                    ),
                    Text(
                      '${ _preferencesHelper.getUserEmail().toString()}',
                      style: TextStyle(
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),

              ListTile(
                title: Text('Smarty',
                  style: TextStyle(
                    color: Colors.white,
                  ),
                ) ,
              ),


              FlatButton(
                onPressed: (){
                  Navigator.pushNamed(context, HomeModule.ROUTE_HOME);
                },
                child: ListTile(
                  title: Text("Home",
                  style: TextStyle(
                    color: Colors.white,
                  ),
                  ),
                  trailing: Icon(
                    Icons.home,
                  color: Colors.white,
                  ),
                ),
              ),
              FlatButton(
                onPressed: (){
                  Navigator.pushNamed(context, HomeModule.ROUTE_COURSE_LIST);
                },
                child: ListTile(
                  title: Text("Courses",
                    style: TextStyle(
                        color: Colors.white,
                    ),
                  ),
                  trailing: Icon(
                      Icons.book,
                    color: Colors.white,
                  ),
                ),
              ),
              FlatButton(
                onPressed: (){
                  Navigator.pushNamed(context, HomeModule.ROUTE_PROGRAMS);
                },
                child: ListTile(
                  title: Text("Programs",
                    style: TextStyle(
                      color: Colors.white,
                    ),
                  ),
                  trailing: Icon(
                    Icons.satellite,
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
